-- Punk Records (RAG): esquema de Supabase.
-- Ejecutar en el editor SQL de Supabase (Project → SQL Editor → New query).
-- Ver docs/200_DesignPlan_Asistente.md, sección 4.1.

create extension if not exists vector;

create table punkrecords_fragmentos (
  id bigint generated always as identity primary key,
  fuente text not null,              -- v1: tecnica, guia (futuras: objeto, akuma, isla, virtud, sabiasque, anuncio)
  ref text not null,                 -- ID en el origen (T123, nombre de archivo)
  parte smallint not null default 0, -- número de fragmento dentro de la referencia
  titulo text not null,
  url text not null default '',
  texto text not null,               -- con el ID y el nombre al inicio
  hash text not null,                -- SHA-1 del texto: indexado incremental
  embedding vector(1024), -- antes 768 (Gemini); 1024 es la dimensión por defecto de voyage-4
  tsv tsvector generated always as
    (to_tsvector('spanish', coalesce(titulo,'') || ' ' || texto)) stored,
  actualizado_en timestamptz not null default now(),
  unique (fuente, ref, parte)
);
create index punkrecords_fragmentos_tsv on punkrecords_fragmentos using gin (tsv);
create index punkrecords_fragmentos_emb on punkrecords_fragmentos using hnsw (embedding vector_cosine_ops);
alter table punkrecords_fragmentos enable row level security;  -- sin políticas: solo la clave secreta (service_role) accede

-- Búsqueda híbrida: texto en español (tsvector) + similitud semántica (pgvector),
-- fusionadas con Reciprocal Rank Fusion (RRF).
--
-- Nota sobre 'consulta_limpia': las palabras interrogativas con tilde
-- (cómo, qué, cuál, dónde, cuándo...) NO están en la lista de palabras
-- vacías del español de Postgres (sus versiones sin tilde sí), así que
-- sobreviven como términos obligatorios en websearch_to_tsquery (que
-- combina los términos con AND) y una pregunta que empiece con "¿Cómo...?"
-- nunca hace match con texto declarativo que jamás usa esa palabra. Se
-- quitan antes de construir la tsquery.
create function buscar_hibrido(consulta text, consulta_emb vector(1024) default null, k int default 10)
returns table (id bigint, fuente text, ref text, titulo text, url text, texto text,
               puntaje double precision, similitud double precision)
language sql stable as $$
  with consulta_limpia as (
    select regexp_replace(
      consulta,
      '\y(cómo|como|qué|que|cuál|cual|cuáles|cuales|cuándo|cuando|cuánto|cuanto|cuánta|cuanta|cuántos|cuantos|cuántas|cuantas|quién|quien|quiénes|quienes|dónde|donde)\y',
      '', 'gi'
    ) as texto
  ),
  t as (
    select id, row_number() over (order by ts_rank_cd(tsv, q) desc) as r
    from punkrecords_fragmentos,
         websearch_to_tsquery('spanish', (select texto from consulta_limpia)) q
    where tsv @@ q limit 20),
  s as (
    select id, row_number() over (order by embedding <=> consulta_emb) as r,
           1 - (embedding <=> consulta_emb) as sim
    from punkrecords_fragmentos
    where consulta_emb is not null and embedding is not null
    order by embedding <=> consulta_emb limit 20)
  select f.id, f.fuente, f.ref, f.titulo, f.url, f.texto,
         coalesce(1.0/(60+t.r),0) + coalesce(1.0/(60+s.r),0) as puntaje,
         coalesce(s.sim, 0) as similitud
  from punkrecords_fragmentos f
  left join t using (id) left join s using (id)
  where t.id is not null or s.id is not null
  order by puntaje desc limit k;
$$;

-- Permisos para service_role. Necesarios aunque service_role salte el RLS:
-- saltarse el RLS no exime de los permisos base de SQL sobre la tabla.
-- Sin esto, Supabase devuelve 403 "permission denied for table ...".
GRANT SELECT, INSERT, UPDATE, DELETE ON public.punkrecords_fragmentos TO service_role;
GRANT USAGE, SELECT ON SEQUENCE public.punkrecords_fragmentos_id_seq TO service_role;
GRANT EXECUTE ON FUNCTION public.buscar_hibrido(text, vector, int) TO service_role;

-- Migración: cambio de proveedor de embeddings, Gemini (768) → Voyage voyage-4 (1024).
-- Solo para una base que YA tenía la tabla con vector(768); en una instalación
-- nueva desde cero, el CREATE TABLE de arriba ya usa 1024 directamente.
drop index if exists punkrecords_fragmentos_emb;
alter table punkrecords_fragmentos drop column embedding;
alter table punkrecords_fragmentos add column embedding vector(1024);
create index punkrecords_fragmentos_emb on punkrecords_fragmentos using hnsw (embedding vector_cosine_ops);

create or replace function buscar_hibrido(consulta text, consulta_emb vector(1024) default null, k int default 10)
returns table (id bigint, fuente text, ref text, titulo text, url text, texto text,
               puntaje double precision, similitud double precision)
language sql stable as $$
  with consulta_limpia as (
    select regexp_replace(
      consulta,
      '\y(cómo|como|qué|que|cuál|cual|cuáles|cuales|cuándo|cuando|cuánto|cuanto|cuánta|cuanta|cuántos|cuantos|cuántas|cuantas|quién|quien|quiénes|quienes|dónde|donde)\y',
      '', 'gi'
    ) as texto
  ),
  t as (
    select id, row_number() over (order by ts_rank_cd(tsv, q) desc) as r
    from punkrecords_fragmentos,
         websearch_to_tsquery('spanish', (select texto from consulta_limpia)) q
    where tsv @@ q limit 20),
  s as (
    select id, row_number() over (order by embedding <=> consulta_emb) as r,
           1 - (embedding <=> consulta_emb) as sim
    from punkrecords_fragmentos
    where consulta_emb is not null and embedding is not null
    order by embedding <=> consulta_emb limit 20)
  select f.id, f.fuente, f.ref, f.titulo, f.url, f.texto,
         coalesce(1.0/(60+t.r),0) + coalesce(1.0/(60+s.r),0) as puntaje,
         coalesce(s.sim, 0) as similitud
  from punkrecords_fragmentos f
  left join t using (id) left join s using (id)
  where t.id is not null or s.id is not null
  order by puntaje desc limit k;
$$;

GRANT EXECUTE ON FUNCTION public.buscar_hibrido(text, vector, int) TO service_role;

-- Migración: buscar_hibrido() ahora también devuelve 'rank_texto' (la
-- posición del fragmento en el ranking por palabras clave, o null si no
-- matcheó por texto). Antes solo devolvía 'similitud' (coseno de embedding),
-- que es 0 para cualquier fragmento que solo haya entrado por texto — con
-- eso, un filtro de relevancia mínima en PHP no podía distinguir "esto entró
-- por texto con muy buen match" de "esto entró de pura casualidad posicional
-- del RRF" (caso real: la pregunta "Hola Teniente Shark!" trajo técnicas de
-- tiburones solo por la coincidencia de esa palabra). Con 'rank_texto'
-- disponible, PHP puede conservar un fragmento si tiene ALTA similitud de
-- embedding, O quedó entre los primerísimos puestos del texto — no solo
-- "entró en el top 20 de alguna de las dos búsquedas".
--
-- CREATE OR REPLACE no permite cambiar las columnas de RETURNS TABLE; hay
-- que borrar la función primero.
drop function if exists buscar_hibrido(text, vector, int);

create function buscar_hibrido(consulta text, consulta_emb vector(1024) default null, k int default 10)
returns table (id bigint, fuente text, ref text, titulo text, url text, texto text,
               puntaje double precision, similitud double precision, rank_texto int)
language sql stable as $$
  with consulta_limpia as (
    select regexp_replace(
      consulta,
      '\y(cómo|como|qué|que|cuál|cual|cuáles|cuales|cuándo|cuando|cuánto|cuanto|cuánta|cuanta|cuántos|cuantos|cuántas|cuantas|quién|quien|quiénes|quienes|dónde|donde)\y',
      '', 'gi'
    ) as texto
  ),
  t as (
    select id, row_number() over (order by ts_rank_cd(tsv, q) desc) as r
    from punkrecords_fragmentos,
         websearch_to_tsquery('spanish', (select texto from consulta_limpia)) q
    where tsv @@ q limit 20),
  s as (
    select id, row_number() over (order by embedding <=> consulta_emb) as r,
           1 - (embedding <=> consulta_emb) as sim
    from punkrecords_fragmentos
    where consulta_emb is not null and embedding is not null
    order by embedding <=> consulta_emb limit 20)
  select f.id, f.fuente, f.ref, f.titulo, f.url, f.texto,
         coalesce(1.0/(60+t.r),0) + coalesce(1.0/(60+s.r),0) as puntaje,
         coalesce(s.sim, 0) as similitud,
         t.r as rank_texto
  from punkrecords_fragmentos f
  left join t using (id) left join s using (id)
  where t.id is not null or s.id is not null
  order by puntaje desc limit k;
$$;

GRANT EXECUTE ON FUNCTION public.buscar_hibrido(text, vector, int) TO service_role;
