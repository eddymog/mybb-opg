<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'tecnicas_show.php');
require_once "./../global.php";
require "./../inc/config.php";
require_once MYBB_ROOT."/inc/plugins/lib/spoiler.php";
global $templates;

$estilo_banner = strtoupper($mybb->get_input('estilo')); 
$estilo = str_replace("_", " ", $estilo_banner);

// $query_clan = $db->query("
// SELECT * FROM mybb_op_clanes WHERE nombreClan='$estilo'
// ");

$clase_descripcion = '';

if ($estilo == 'COMBATIENTE' || $estilo == 'ESPADACHÍN' || $estilo == 'GUERRERO' || $estilo == 'TIRADOR' || $estilo == 'ARQUERO' || $estilo == 'ARTILLERO' ||
    $estilo == 'TECNICISTA' || $estilo == 'PÍCARO' || $estilo == 'ARTISTA' || $estilo == 'ESCUDERO' || $estilo == 'ASESINO' || $estilo == 'ARTISTA MARCIAL') {
    $query_tecnicas = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE clase='$estilo' OR rama='$estilo' ORDER BY CAST(tier AS UNSIGNED) ASC ");
} else if ($estilo == 'ELEMENTAL' || $estilo == 'HAKI' || $estilo == 'RACIAL' || $estilo == 'ESPECIAL') {
    $query_tecnicas = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE estilo='$estilo' ORDER BY CAST(tier AS UNSIGNED) ASC ");
} else {
    $query_tecnicas = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='$estilo' ORDER BY CAST(tier AS UNSIGNED) ASC ");
}

$estilo2 = '';
$estilo3 = '';
$estilo4 = '';

if ($estilo == 'COMBATIENTE') {
    $query_tecnicas2 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='BERSERKER' ORDER BY CAST(tier AS UNSIGNED) ASC ");
    $query_tecnicas3 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='CAMPEÓN' ORDER BY CAST(tier AS UNSIGNED) ASC ");

    $estilo2 = 'BERSERKER';
    $estilo3 = 'CAMPEÓN';

    $clase_descripcion = "El Combatiente es la representación del equilibrio marcial. Alterna entre ataque y defensa con naturalidad, fortaleciéndose cuanto más dura el combate. Esta disciplina permite resistir, contraatacar y crecer durante el enfrentamiento, haciendo del desgaste su mayor ventaja. Ideal para quienes no temen prolongar el combate, el Combatiente se convierte en una fuerza ascendente que se adapta, se recupera y supera los límites conforme la batalla se intensifica. Persistente, tenaz y en constante evolución. <strong>Sus técnicas requieren \"Cualquier tipo de Arma\" para su ejecución. \"DISCIPLINA INCOMPATIBLE CON ASESINO\" </strong>";
}

if ($estilo == 'ESPADACHÍN') {
    $query_tecnicas2 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='SAMURÁI' ORDER BY CAST(tier AS UNSIGNED) ASC ");
    $query_tecnicas3 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='MOSQUETERO' ORDER BY CAST(tier AS UNSIGNED) ASC ");

    $estilo2 = 'SAMURÁI';
    $estilo3 = 'MOSQUETERO';

    $clase_descripcion = "Maestros de la espada y las armas de filo, los Espadachines combinan técnica, velocidad y decisión letal en cada corte. Capaces de desgastar con precisión o de asestar golpes letales con un solo tajo, dominan el arte del duelo y el control del flujo de combate. Esta disciplina permite estilos refinados que castigan errores, debilitan al enemigo mediante hemorragias y conservan un equilibrio entre movilidad y agresión. Su hoja es una extensión de su voluntad. <strong>Sus técnicas requieren \"Armas de Filo\" para su ejecución.</strong>";
}

if ($estilo == 'GUERRERO') {
    $query_tecnicas2 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='CASTIGADOR' ORDER BY CAST(tier AS UNSIGNED) ASC ");
    $query_tecnicas3 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='WARHAMMER' ORDER BY CAST(tier AS UNSIGNED) ASC ");

    $estilo2 = 'CASTIGADOR';
    $estilo3 = 'WARHAMMER';

    $clase_descripcion = "Los Guerreros son el rostro del combate directo. Empuñando armas pesadas y devastadoras, avanzan con fuerza imparable, dispuestos a quebrar líneas enemigas y arrasar con lo que se interponga en su camino. Esta disciplina se especializa tanto en la defensa reactiva como en la ofensiva total, permitiendo resistir con firmeza o desatar una tormenta de destrucción. Cada movimiento tiene peso, y cada golpe busca dejar huella en el campo de batalla. <strong>Sus técnicas requieren \"Armas Contundentes\" para su ejecución.</strong>";
}

if ($estilo == 'TIRADOR') {
    $query_tecnicas2 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='DUELISTA' ORDER BY CAST(tier AS UNSIGNED) ASC ");
    $query_tecnicas3 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='FRANCOTIRADOR' ORDER BY CAST(tier AS UNSIGNED) ASC ");

    $estilo2 = 'DUELISTA';
    $estilo3 = 'FRANCOTIRADOR';
    $clase_descripcion = "Especializados en el combate a distancia, los Tiradores hacen del disparo su arte. Con armas de fuego en mano, eligen con cuidado cada blanco y adaptan su posición para controlar el terreno desde lejos. Esta disciplina permite dominar tanto el duelo cercano con armas ligeras como el asesinato silencioso a larga distancia. Su puntería es letal, y su capacidad de presión hace que el enemigo se mantenga siempre a cubierto o en retirada. <strong>Sus técnicas requieren \"Armas de Fuego\" para su ejecución.</strong>";
}

if ($estilo == 'ARQUERO') {
    $query_tecnicas2 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='BALLESTERO' ORDER BY CAST(tier AS UNSIGNED) ASC ");
    $query_tecnicas3 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='CAZADOR' ORDER BY CAST(tier AS UNSIGNED) ASC ");

    $estilo2 = 'BALLESTERO';
    $estilo3 = 'CAZADOR';

    $clase_descripcion = "Los Arqueros son cazadores expertos que dominan la distancia como su mayor ventaja. Con arcos y ballestas, pueden vigilar el campo, acosar a su presa o anular movimientos clave del enemigo. Cada flecha cuenta, y cada disparo es fruto del cálculo y la intención precisa. Esta disciplina ofrece control, daño constante y la capacidad de dictar el ritmo del combate desde la lejanía, acechando sin cesar hasta que el enemigo cede. <strong>Sus técnicas requieren \"Armas de Tensión\" para su ejecución.</strong>";
}

if ($estilo == 'ARTILLERO') {
    $query_tecnicas2 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='DESTRUCTOR' ORDER BY CAST(tier AS UNSIGNED) ASC ");
    $query_tecnicas3 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='JUGGERNAUT' ORDER BY CAST(tier AS UNSIGNED) ASC ");

    $estilo2 = 'DESTRUCTOR';
    $estilo3 = 'JUGGERNAUT';
    $clase_descripcion = "Los Artilleros son el caos encarnado en el campo de batalla. Utilizan armas pesadas, explosivos y metralla para desatar el pánico, destruir formaciones y abrir paso entre los escombros. Esta disciplina se especializa en controlar el espacio mediante fuego continuo o impactos demoledores. Ya sea mediante lanzagranadas, cañones o armas de asalto, un Artillero es una amenaza constante que transforma cada combate en una zona de guerra. <strong>Sus técnicas requieren \"Armas de Asalto\" para su ejecución.</strong>";
}

if ($estilo == 'TECNICISTA') {
    $query_tecnicas2 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='DILETANTE' ORDER BY CAST(tier AS UNSIGNED) ASC ");
    $query_tecnicas3 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='WEAPONMASTER' ORDER BY CAST(tier AS UNSIGNED) ASC ");

    $estilo2 = 'DILETANTE';
    $estilo3 = 'WEAPONMASTER';

    $clase_descripcion = "Los Tecnicistas representan la maestría en armas poco convencionales. Desde lanzas hasta herramientas exóticas, su versatilidad les permite adaptarse a cualquier combate. Esta disciplina premia la creatividad, la lectura del entorno y la capacidad de cambiar de táctica en un instante. Perfectos para quienes disfrutan de la estrategia y el dominio técnico, los Tecnicistas se convierten en combatientes impredecibles, capaces de romper esquemas y responder a cualquier situación con una solución afilada. <strong>Sus técnicas requieren \"Armas Exóticas, Flexibles y de Asta\" para su ejecución.</strong>";
}

if ($estilo == 'PÍCARO') {
    $query_tecnicas2 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='GAMBITO' ORDER BY CAST(tier AS UNSIGNED) ASC ");
    $query_tecnicas3 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='TRICKSTER' ORDER BY CAST(tier AS UNSIGNED) ASC ");

    $estilo2 = 'GAMBITO';
    $estilo3 = 'TRICKSTER';

    $clase_descripcion = "Los Pícaros son expertos en el engaño y la oportunidad. Usan armas arrojadizas, tácticas poco convencionales y movimientos impredecibles para sacar ventaja en combate. Esta disciplina destaca por su capacidad de interrumpir, confundir y castigar errores con rapidez. Cada ataque puede ser directo o una distracción con efectos ocultos. Perfecta para jugadores creativos, su fuerza reside en la sorpresa, el control del ritmo y la constante presión mental sobre el oponente. <strong>Sus técnicas requieren \"Armas Arrojadizas\" para su ejecución.</strong>";
}

if ($estilo == 'ARTISTA') {
    $query_tecnicas2 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='BARDO' ORDER BY CAST(tier AS UNSIGNED) ASC ");
    $query_tecnicas3 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='TROVADOR' ORDER BY CAST(tier AS UNSIGNED) ASC ");

    $estilo2 = 'BARDO';
    $estilo3 = 'TROVADOR';

    $clase_descripcion = "Los Artistas canalizan su espíritu a través de la música, influyendo en el campo de batalla con cada nota. Ya sea para fortalecer a sus aliados o debilitar al enemigo, su presencia cambia el tono del combate. Esta disciplina combina control táctico, apoyo versátil y presencia escénica. Los efectos de su arte son tan visibles como potentes, capaces de convertir el caos en orden o el valor en duda. Su voz o instrumento es su mejor arma. <strong>Sus técnicas requieren \"Instrumentos\" para su ejecución.</strong>";
}

if ($estilo == 'ESCUDERO') {
    $query_tecnicas2 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='VANGUARDIA' ORDER BY CAST(tier AS UNSIGNED) ASC ");
    $query_tecnicas3 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='BASTIÓN' ORDER BY CAST(tier AS UNSIGNED) ASC ");

    $estilo2 = 'VANGUARDIA';
    $estilo3 = 'BASTIÓN';

    $clase_descripcion = "Los Escuderos representan el arquetipo defensivo por excelencia. Utilizan escudos no solo para protegerse, sino para formar una línea de defensa impenetrable que resguarda a sus aliados en los momentos más críticos. Son capaces de bloquear ataques devastadores, resistir estados alterados y mantenerse firmes aun cuando todo parece perdido. Su estilo puede ser ágil y reactivo o completamente estático, pero en ambos casos, son el muro que sostiene al grupo y que raramente cede terreno. <strong>Sus técnicas requieren \"Armas Defensivas\" para su ejecución.</strong>";
}

if ($estilo == 'ARTISTA MARCIAL') {
    $query_tecnicas2 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='ACRÓBATA' ORDER BY CAST(tier AS UNSIGNED) ASC ");
    $query_tecnicas3 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='MONJE' ORDER BY CAST(tier AS UNSIGNED) ASC ");

    $estilo2 = 'ACRÓBATA';
    $estilo3 = 'MONJE';

    $clase_descripcion = "Los Artistas Marciales son combatientes que perfeccionan el cuerpo como su principal arma. A través de un riguroso entrenamiento físico y espiritual, desarrollan técnicas de combate sin armas, centradas en la precisión, la velocidad y la contundencia. Esta disciplina se adapta con soltura al entorno y al ritmo del combate, permitiendo al usuario alternar entre posturas defensivas y ofensivas con fluidez. Ya sea mediante el bloqueo certero o el movimiento imprevisible, dominan el campo de batalla con equilibrio y control absoluto. <strong>Sus técnicas requieren \"Armas Corporales\" para su ejecución.</strong>";
}

if ($estilo == 'ASESINO') {
    $query_tecnicas2 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='SOMBRA' ORDER BY CAST(tier AS UNSIGNED) ASC ");
    $query_tecnicas3 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='VERDUGO' ORDER BY CAST(tier AS UNSIGNED) ASC ");

    $estilo2 = 'SOMBRA';
    $estilo3 = 'VERDUGO';

    $clase_descripcion = "Silenciosos, certeros y letales, los Asesinos aprovechan el sigilo, la sorpresa y la precisión quirúrgica para eliminar amenazas antes de que puedan reaccionar. Estudian cada movimiento del enemigo para atacar justo en el momento crítico, buscando puntos vitales y flancos débiles. Esta disciplina es perfecta para quienes prefieren la estrategia y la ejecución exacta por encima del combate abierto. Desde las sombras, cambian el destino del enfrentamiento con un solo golpe certero. <strong>Sus técnicas requieren \"Cualquier tipo de Arma\" para su ejecución. \"DISCIPLINA INCOMPATIBLE CON COMBATIENTE\" </strong>";
}

if ($estilo == 'GUNKATA') {
    $clase_descripcion = "Un estilo versátil que combina el cuerpo a cuerpo con las armas a distancia. Nacido en el South Blue, sus usuarios iniciales crearon este estilo para combatir en los terrenos escarpados y montañosos de donde proceden. Es un estilo que busca alternar la media y la corta distancia, con potentes ataques cuerpo a cuerpo y un buen repertorio de ataques en área.";
}

if ($estilo == 'HASSHOKEN') {
    $clase_descripcion = "El estilo Hasshoken permite al usuario generar ondas que transmiten el daño de sus golpes ya sea cuerpo a cuerpo o con armas contundentes. Dichas ondas permiten dañar internamente, siendo especialmente efectivas contra defensas pasivas o técnicas defensivas. El estilo procede del Reino de Kano del West Blue, muy común en los integrantes de su ejército.";
}

if ($estilo == 'ASHURA SANTORYU') {
    $clase_descripcion = "Los usuarios de este estilo dominan el arte de luchar con hasta tres espadas, pero también dominan todas las etapas previas como el estilo de una y de dos espadas, por lo tanto es habitual que según el transcurso del combate adapten la cantidad de espadas que utilizan para obtener el mejor resultado de cada situación.  El estilo procede de la isla Demon Tooth en el East Blue, se creó hace muchísimos años en el diente este, en el dojo Jigoku no Tsuno.";
}

if ($estilo == 'KUROASHI') {
    $clase_descripcion = "Los miembros de este estilo utilizan sus piernas para golpear renunciando así a toda la potencia de sus extremidades superiores, que si utilizarán frecuentemente para posicionarse o generar apoyos. Son sumamente hábiles encontrando aperturas y encadenado golpes buscando atosigar al enemigo para no darle un instante de descanso. El estilo procede del North blue aunque tiene un dojo en el diente oeste de la isla Demon Tooth del East Blue. [Afín al elemento Pyro]";
}

if ($estilo == 'GYOJIN KARATE') {
    $clase_descripcion = "El karate gyojin es un arte marcial cuya maestría radica en el dominio del agua en las inmediaciones del usuario, usando su poder para enviar poderosas ondas que impactan el agua dentro del cuerpo del oponente. Debido a que cada ser tiene un alto porcentaje de agua dentro de su cuerpo. Se centra mas en acompañar los golpeos con el agua, no en mover grandes cantidades de agua como hace el Gyojin Jujutsu [Afín al Elemento Aqua].";
}

if ($estilo == 'GYOJIN JUJUTSU') {
    $clase_descripcion = "Este estilo es  un arte marcial practicado por la raza de los Gyojin/Ningyo, pareciéndose al Karate Gyojin. Se trata de un estilo subacuático que implica la manipulación del entorno como si fuera un material sólido. Es desconocido cómo los Gyojin son capaces de manipularla pero da una gran ventaja bajo el agua. Se centra en manipular grandes cantidades de agua y darles forma a estas [Afín al Elemento Aqua].";
}

if ($estilo == 'GYOJIN BUKIJUTSU') {
    $clase_descripcion = "El bukijutsu gyojin es un estilo de combate que logra dominar el agua a través de diversas armas cortantes o punzantes, canalizándola con el fin de acentuar los cortes y estocadas del estilo mediante pulsaciones marinas. Las ofensivas logran cortar el agua que compone todos los cuerpos vivos, causándole un gran daño a nivel interno en el organismo, siendo un estilo equilibrado entre el Karate y el Jujutsu en el manejo de las masas de agua [Afín al Elemento Aqua]. ";
}

if ($estilo == 'OKAMA KEMPO') {
    $clase_descripcion = "Este estilo de combate utiliza principalmente patadas y es tomado directamente del ballet, ya que la mayoría de sus movimientos se basan en posiciones de baile ballet. En este modo de combate el estilo lo es todo, proyectar una imagen elegante y original es la base de todo. El Okama Kempo le permite al usuario golpear y moverse con más soltura cuanto mayor sea su estilo y más haga gala de él.";
}

if ($estilo == 'SORA YOKUJIN') {
    $clase_descripcion = "Los practicantes de este estilo son expertos combatientes a distancia haciendo uso normalmente de un arco, aunque en algunas ocasiones pueden utilizar otro tipo de armas rudimentarias. Han logrado conectar en armonía con el elemento aire. Su estilo se basa en utilizar la altura para lanzar sus flechas y en manipular el aire mediante sus silbidos con los cuales ayudan a sus flechas a impulsarse y trazar movimientos complejos [Afín al Elemento Aero]. ";
}

if ($estilo == 'ROKUSHIKI') {
    $clase_descripcion = "El Rokushiki es un estilo especial sobrehumano de artes marciales que busca llevar las capacidades físicas del cuerpo humano al límite. Consta de 6 potentes técnicas, cada una más compleja que la anterior. Es muy poco común, muy poco conocido y se suele asociar con la Ciper Pool. Se dice que los expertos en este estilo son capaces de crear sus propias variantes de alguna de las 6 técnicas subiendo aún más el listón.";
}

if ($estilo == 'NINJUTSU') {
    $clase_descripcion = "El objetivo del estilo nace para permitir que los ninjas realicen sus misiones de manera eficiente, principalmente la obtención de información y la eliminación de enemigos. El ninjutsu particularmente es usado por los ninjas en las operaciones de sigilo, ya que varias técnicas de este se enfocan en hacer escapes discretos e incapacitar rápidamente a los enemigos. En el Ninjutsu es común el uso de utensilios, los despistes, salir y entrar de combate y la versatilidad.";
}

if ($estilo == 'RYUSOKEN') {
    $clase_descripcion = "El arte marcial Ryusoken se centra en la colocación de los dedos de la mano para formar una «garra» que aplasta con una gran fuerza todo lo que esté a su alcance. Este estilo destaca por su gran capacidad destructiva, muy por encima de la de otros estilos, tanto para la destrucción de armas u objetos como para la aniquilación total de estructuras. ";
}

if ($estilo == 'JIYUUMURA KEMPO') {
    $clase_descripcion = "Es el estilo insignia del ejército revolucionario, se basa en la cooperación y el compañerismo como arma para conseguir el bien común y la justicia general.  Busca llevar al cuerpo del usuario más allá de sus límites entendiendo y aplicando puntos de presión en su cuerpo y en el de sus compañeros para aumentar su fuerza, su velocidad o su resistencia en determinadas acciones. Todos los miembros de la revolución se mueven a una y trabajan en conjunto alentados por su unidad. ";
}

if ($estilo == 'POP GREEN') {
    $clase_descripcion = "Las Pop Green es una forma de combate especializada que recurre a poderosas semillas capaces de generar plantas de asombrosa rapidez y efectos variados. Nacido entre tiradores y luchadores que dominan la larga distancia, este estilo utiliza las 'Pop Greens' para transformar el campo de batalla en un entorno hostil y lleno de sorpresas. Debido a su naturaleza volátil y peligrosa, estas semillas exigen una alta precisión y un conocimiento experto para manipular sus propiedades de forma segura. Los practicantes del Pop Green convierten el control de la zona en su arma principal, enfrentando a sus enemigos a una distancia segura y estratégica. ";
}

if ($estilo == 'CLIMA TACT') {
    $clase_descripcion = "El Clima Tact combina la ciencia y la precisión meteorológica para crear fenómenos climáticos en pleno combate. Utilizando un cetro altamente sofisticado, los usuarios pueden manipular la atmósfera, generando desde intensas tormentas hasta deslumbrantes relámpagos. Originado por un grupo de meteorólogos pioneros, este arte marcial requiere un profundo conocimiento del clima y una mente ágil para dominar las condiciones atmosféricas a su favor. Solo navegantes expertos, aquellos que comprenden las complejidades del mundo natural, pueden aprovechar plenamente el potencial devastador y versátil del Clima Tact. ";
}

if ($estilo == 'FUNEKIRI') {
    $clase_descripcion = "Conocido como el “corte de navíos”, el Funekiri es un estilo de esgrima especializado que se basa en liberar tajos de energía cortante capaces de recorrer grandes distancias. Creado originalmente en la Marina para combatir en el mar, este estilo permite a los espadachines lanzar poderosos ataques a presión de aire, causando destrucción masiva sin la necesidad de acercarse al enemigo. Cada corte viaja a gran velocidad, variando de dirección y forzando al oponente a mantenerse en constante alerta. El Funekiri es ideal para combates en alta mar, donde la movilidad y el alcance son esenciales para superar las distancias y los obstáculos. ";
}

if ($estilo == 'HAKAI SHIN') {
    $clase_descripcion = "El Hakai Shin es una demostración de fuerza bruta y control total de armas pesadas, donde la violencia se convierte en la única forma de expresión en el campo de batalla. Este estilo salvaje fue popularizado por la tribu de mercenarios Oni de Onigashima, quienes ven en el Hakai Shin una herramienta para mostrar su temible poder. Caracterizado por el uso de armas colosales y técnicas que impactan brutalmente al oponente, este estilo intimida a muchos, ganando reputación de ser pura barbarie. El Hakai Shin demanda una enorme resistencia y ferocidad, haciéndolo un arte temido y respetado por su brutalidad indomable [Afín al Elemento Electro].";
}

if ($estilo == 'RAILGUN STYLE') {
    $clase_descripcion = "Mediante la infusión de la propia electricidad que recorre el cuerpo del practicante de este estilo, será capaz de cargar sus proyectiles para lanzar disparos electrificados que buscan alcanzar los cuerpos de sus victimas y desestabilizar su sistema nervioso. Este estilo de combate se comenzó a desarrollar hace muchos por una generación de arqueras Kuja en sus flechas, pero el mismo fue extendiéndose por el mar a partir de ellas y puliéndose al expandirse a otras armas a distancia [Afín al Elemento Electro].";
}

if ($estilo == 'SHURON HAKKE') {
    $clase_descripcion = "Nadie sabe en qué momento de la historia se originó este estilo de combate, ni quien lo comenzó, algunos creen que es algo que todo borracho lleva dentro de sí esperando a ser despertado. Los que logran alcanzar este estado casi místico serán capaces de beber sin ningún control, alcanzando con toda la facilidad del mundo el estado de ebriedad. En dicho estado pasarán por todas las fases y estados posibles de la borrachera, logrando pelear de forma impredecible, caótica y alcohólica.";
}

if ($estilo == 'RAQISAT ALSAHRA') {
    $clase_descripcion = "El estilo de los corredores del desierto, unos velocistas que se mueven por las arenas aprovechando la velocidad del viento. Se dice que pueden desplazarse entre las tormentas de arena como cuchillas ocultas, deslizándose para ejecutar a sus enemigos. Se trata de un estilo que actualmente se practica en todas las zonas áridas o desérticas del mundo, pero que su origen se encuentra en la ancestral tierra de Arabasta, donde lo practicaban todos aquellos guerreros ajenos al ejército real que se desplazaban entre las poblaciones separadas por el gran desierto. [Afín al Elemento Aero].";
}

if ($estilo == 'BREESKJOLD') {
    $clase_descripcion = "La mejor defensa del mundo, los mayores guerreros provenientes de la gélida Elbaph. Los gigantes curtidos en el frío Inframundo han desarrollado un estilo defensivo basado en soportar las acometidas del enemigo mientras sus cuerpos se van congelando, cediendo a la frialdad de los poderosos guerreros hasta que su enemigo cae de rodillas al suelo volviéndose parte del glacial. Un arte del combate muy popularizado entre los gigantes, aunque no son los únicos que lograron desarrollar esta capacidad [Afín al Elemento Cryo].";
}

if ($estilo == 'IMPACTO EXPLOSIVO') {
    $clase_descripcion = "La gran fricción producto de un golpe directo, rápido y poderoso, logra que el puño arda con gran intensidad hasta estallar el impacto contra su adversario en un arte marcial que puede destrozar completamente a un enemigo acorazado a base de hacerlo explotar en pedazos. Se dice que desde su origen en el South Blue, los primeros usuarios de este estilo de lucha, esculpían las escarpadas montañas de Layahima a base de golpes directos contra el perfil de la montaña [Afín al Elemento Piro].";
}

if ($estilo == 'ROYAL GUARD') {
    $clase_descripcion = "Todo reino dispone de ejércitos propios, y aunque algunos tienen estilos muy particulares y comunes, en multitud de reinos se ha extendido un estilo de combate caballeresco y centrado en la defensa y en aguardar por la agresión del contrario. Una esgrima, a pesar de no requerir necesariamente de una espada, que se especializa en utilizar la fuerza y agresividad del rival en su contra para reducirlo y neutralizarlo. Elegante, defensivo y letal, son los términos que definen a la ultima línea de defensa de los reyes.";
}

if ($estilo == 'SHIKAKU TEIKOKU') {
    $clase_descripcion = "En los bajos fondos siempre se movió gente siniestra, asesinos que te clavan un puñal por la espalda. Es una práctica común en el bajo mundo, entrenar las artes del sigilo y el asesinato con el fin de acabar con la competencia, o simplemente para ganarse la vida, librándose de los estorbos de los peces gordos. Pero no solo ocurre en las cloacas de la sociedad, en multitud de monarquias se han formado pequeños grupos de asesinos con este arte con el fin de hacer el trabajo sucio del reino, siguiendo el ejemplo del Cipher Pol.";
}

if ($estilo == 'DUELLISTE DE GIVRE') {
    $clase_descripcion = "La esgrima practicada por ciertos grupos ocultistas y esotéricos de todo el mundo, pero especialmente concentrados en regiones de la Grand Line y el West Blue, que logran canalizar en sus armas una materialización física del gélido aire del inframundo, logrando transmitir con sus ofensivas un peligroso estado de congelamiento que hace sucumbir a un letargo profundo a sus víctimas con el fin de brindarles el sueño eterno y atarlos de forma permanente al inframundo [Afín al Elemento Cryo].";
}

if ($estilo == 'FILO DELLA VITA') {
    $clase_descripcion = "Un extraño estilo que se originó en la cima del Layahima en el South Blue, practicado por los monjes que lograron controlar su cabello hasta tal punto que lo volvieron un arma para la luchar. Usándolo como una extensión más de su cuerpo y siendo expertos en el arte del Seimei Kitan, lo que les permite alterar y modificar su fisionomía, aunque el estilo se fue diluyendo al descender de la montaña, consiguió perdurar en las regiones alejadas del templo.";
}

if ($estilo == 'HAVETS SYMFONI') {
    $clase_descripcion = "Desde tiempos inmemoriales, es conocido por todos los marineros el poder del canto de las sirenas para cautivar a los tripulantes y piratas en alta mar, siendo este estilo de combate uno originado por las miembros de esta raza y expandiéndolo a lo largo del mar, adaptando sus canciones y sinfonías a diversos estilos y formas acorde a las aguas donde se encontraran, puesto que no solo cautivaba su voz, sino también el estilo musical. Con los años, los juglares y bardos aprendieron de las sirenas y adoptaron este estilo para ellos, logrando ciertas variaciones musicales.";
}

if ($estilo == 'BAKUDAI KARIN') {
    $clase_descripcion = "El arte de la pólvora y la ignición. Este estilo fue originado en una remota isla volcanica del Nuevo Mundo, donde constamente diez volcances estan en erupción, liberando azufre en su ecosistema. Sus habitantes aprendieron a imbuir llamas condensadas en un polvo anaranjado que con el contacto con el aire se va tornando carmesi hasta causar una ignición. Desde entonces, en toda zona volcánica se practica el imbuir ciertos proyectiles de armas con pólvora de esta sustancia, logrando una pirotécnica con alto poder incandescente [Afín al Elemento Piro].";
}

if ($estilo == 'ELEMENTAL') {
    $clase_descripcion = "Los dominios elementales son unas capacidades sobrehumanas que algunos seres logran desarollar mediante la cual con su cuerpo son capaces de emitir y liberar alguno de los elementos primarios del mundo. Algunos de ellos son característicos de ciertas razas, aunque no significa que sea un poder exclusivo de ellos. La incorporación de estas capacidades elementales vuelve muy peligroso a un luchador, puesto que logran grandes hazañas incluso sin haber consumido una fruta.";
}

if ($estilo == 'RACIAL') {
    $clase_descripcion = "En el mundo de One Piece existen muchas razas, todas ellas con sus virtudes y defectos innatos, los cuales crean esa distinción entre ellos. Manifestaádose estas peculiaridades en muchos más aspectos que simplemente el aspecto, pudiendo contemplarse habilidades y capacidades extraordinarias en algunas de ellas. Siendo tan diferentes unas de otras, que incluso los seres nacidos de los genes de dos razas diferentes, vulgarmente llamados híbridos, logran ser muy únicos y especiales.";
}

if ($estilo == 'ITTORYU SEKAI') {
    $clase_descripcion = "El Ittoryu Sekai es una variante refinada del estilo de una espada, enfocada en la perfección técnica y el dominio absoluto de cada tajo. Su filosofía se basa en que una sola hoja bien empuñada es suficiente para decidir un combate. Los practicantes de este estilo desarrollan cortes tan potentes como certeros, capaces de abrir defensas, romper estructuras o derribar enemigos con precisión quirúrgica. Se trata de un camino solitario, reservado para quienes buscan el máximo control y poder en una única arma. <strong>Sus técnicas requieren \"Armas de Filo (1 Concretamente)\" para su ejecución.</strong>";
}

if ($estilo == 'WANO NITORYU') {
    $clase_descripcion = "El Wano Nitoryu es un estilo de esgrima nacido en las regiones orientales del País de Wano. Se caracteriza por el uso ágil y fluido de dos espadas, permitiendo una combinación constante de ataque y defensa que exige precisión, fuerza y coordinación. Los usuarios de este estilo giran, cruzan y alternan sus espadas con gracia letal, creando una danza marcial que abruma al enemigo. Su dinamismo lo convierte en una opción versátil y ofensiva para los duelistas más habilidosos. <strong>Sus técnicas requieren \"Armas de Filo (2 Concretamente)\" para su ejecución.</strong>";
}

if ($estilo == 'SHISEIJU') {
    $clase_descripcion = "Inspirado en las Cuatro Bestias Guardianas del Este, Norte, Sur y Oeste, el estilo Shiseiju combina las bases del Kungfu con posturas animales adaptadas al combate moderno. Cada forma imita a una bestia y otorga ventajas únicas: fuerza, agilidad, fluidez o resistencia. Existe además una quinta postura secreta, inspirada en los dugones del mar, que mezcla las demás con un equilibrio oculto. Este estilo permite una adaptación constante y una conexión espiritual con la naturaleza a través del movimiento marcial. <strong>Sus técnicas requieren \"Armas Corporales\" para su ejecución.</strong>";
}

if ($estilo == 'MANO DE TAHUR') {
    $clase_descripcion = "La Mano de Tahur es un estilo único y atrevido que convierte las cartas en proyectiles imbuidas de efecto. Cada palo representa un tipo de acción distinta: ofensiva, evasiva, confusora o potenciada. Originalmente creado por timadores, ladrones y artistas callejeros, este estilo mezcla show, estrategia y engaño. Sus usuarios dominan el arte de lanzar cartas con precisión, improvisar efectos y manipular la percepción del enemigo, convirtiendo cada combate en una apuesta cargada de trucos y giros inesperados. <strong>Sus técnicas requieren \"Armas Arrojadizas, de Tensión y de Fuego\" para su ejecución.</strong>";
}

if ($estilo == 'ARCANOS DEL DESTINO') {
    $clase_descripcion = "Nacido del cruce entre la cartomancia y el combate, los Arcanos del Destino utilizan cartas de tarot como canalizadores de energías arcanas. Este estilo altera las condiciones del combate con cada carta jugada, invocando efectos que refuerzan, debilitan, cambian normas o desencadenan sucesos místicos. Más que un estilo físico, es una manifestación mágica del destino en acción. Los practicantes confían en la sincronicidad y el flujo del combate para modificarlo a su favor con cada revelación del mazo. <strong>Sus técnicas requieren \"Ningún tipo de Armas\" para su ejecución.</strong>";
}

if ($estilo == 'YAMA KURAI') {
    $clase_descripcion = "El Yama Kurai es un estilo devastador que combina el uso de armas cuerpo a cuerpo con una aplicación brutal del Haki de Armadura. Los practicantes de este estilo concentran su poder para aplastar y destruir cualquier obstáculo mediante golpes imbuidos en fuerza espiritual. Su apodo, \"el devorador de montañas\", refleja la intensidad de sus ataques, capaces de reducir a escombros estructuras y enemigos por igual. Es un camino ofensivo y brutal, reservado para quienes priorizan la demolición absoluta del oponente. <strong>Sus técnicas requieren \"Armas de Filo, Contundentes, de Asta, Exóticas y Flexibles\" para su ejecución.</strong>";
}

if ($estilo == 'ASHIGARA GODOY') {
    $clase_descripcion = "Basado en las tradiciones del sumo y reforzado por el Haki de Armadura, el Ashigara Dokoi es una muralla viviente. Su defensa es la más firme conocida, y su capacidad de reflejar daño lo convierte en una pesadilla para atacantes frontales. Este estilo utiliza empujes, bloqueos y cargas con todo el cuerpo, resistiendo con determinación y devolviendo cada intento de daño. Sus practicantes se plantan en el campo como pilares inamovibles, haciendo del aguante y la represalia su mayor virtud. <strong>Sus técnicas requieren \"Armas Corporales y Escudos\" para su ejecución.</strong>";
}

if ($estilo == 'KOKUDAN') {
    $clase_descripcion = "El estilo Kokudan transforma las armas de fuego en herramientas de impacto puro a través del Haki. Los usuarios recubren sus balas con una densa capa de Haki de Armadura, provocando explosiones de poder al impacto. Cada disparo se convierte en un proyectil imparable, capaz de atravesar defensas o provocar reacciones devastadoras. Es un estilo que premia la precisión, la puntería y la concentración, pues cada bala es una inversión de energía considerable. Letal, certero y con una potencia sin igual. <strong>Sus técnicas requieren \"Armas de Tensión, de Fuego, de Asalto y Arrojadizas\" para su ejecución.</strong>";
}

if ($estilo == 'SEA CORSAIR') {
    $clase_descripcion = "Desarrollado entre los mares por corsarios y piratas, el estilo Sea Corsair combina el uso simultáneo de armas de fuego y espadas para adaptarse a los caóticos abordajes navales. Versátil y práctico, permite cambiar de distancia de combate con fluidez, alternando entre ataques contundentes, disparos sorpresa y defensa improvisada. Este estilo nace de la necesidad y la experiencia, encarnando el espíritu imprevisible y salvaje de los mares. Su fuerza radica en la creatividad, la audacia y el dominio de múltiples frentes. <strong>Sus técnicas requieren \"Armas de Filo, Contundentes, Arrojadizas, Exóticas, de Fuego y de Asalto (Dos armas a la vez)\" para su ejecución.</strong>";
}

if ($estilo == 'KODAI NO BUSHIDO') {
    $clase_descripcion = "El Kodai No Bushido es un antiguo arte samurái que une la disciplina marcial con el uso de armas blancas en combate cercano. Su enfoque está en el equilibrio: cada acción ofensiva lleva implícita una defensa, y cada bloqueo prepara el siguiente tajo. Se trata de un estilo elegante, eficiente y respetuoso de la tradición, ideal para quienes buscan control, sobriedad y eficacia. Su fuerza nace de la serenidad y el dominio absoluto del ritmo de combate, sin desperdiciar un solo movimiento. <strong>Sus técnicas requieren \"Armas Corporales, de Filo, Contundentes y de Asta (Dos a la vez)\" para su ejecución.</strong>";
}

if ($estilo == 'CAVALRY WARRIOR') {
    $clase_descripcion = "Este estilo se basa en el combate montado, aprovechando la movilidad, la altura y el ímpetu que otorga una montura en batalla. El Cavalry Warrior entrena cuerpo y montura como una unidad coordinada, capaz de cargar con fuerza, esquivar con agilidad y atacar en movimiento constante. Ideal para escaramuzas y flanqueos, su potencia reside en el control del terreno y la capacidad de mantener la presión sin permanecer estático. Un jinete hábil y entrenado es una fuerza arrolladora en el campo abierto. <strong>Sus técnicas requieren \"Armas de Asta, Flexibles, Filo Pesado, Contundente Pesado, de Tensión, de Fuego, Arrojadizas y de Asalto\" para su ejecución.</strong>";
}

if ($estilo == 'COLOR TRAP') {
    $clase_descripcion = "Color Trap es un estilo basado en la hipnosis visual y la manipulación emocional mediante el color. Utilizando pigmentos, pinceles y proyectiles de pintura, el usuario aplica efectos mentales sobre aliados y enemigos según el color utilizado. Este estilo permite alterar el estado anímico, la percepción y la voluntad de los combatientes, creando ventajas tácticas únicas. Más que dañar directamente, se enfoca en alterar el flujo del combate con estímulos sensoriales, debilitando voluntades o potenciando emociones con precisión artística. <strong>Sus técnicas requieren \"Armas Kit del Artista\" para su ejecución.</strong>";
}

if ($estilo == 'ASHIGARA DOKOI') {
    $clase_descripcion = "Basado en las tradiciones del sumo y reforzado por el Haki de Armadura, el Ashigara Dokoi es una muralla viviente. Su defensa es la más firme conocida, y su capacidad de reflejar daño lo convierte en una pesadilla para atacantes frontales. Este estilo utiliza empujes, bloqueos y cargas con todo el cuerpo, resistiendo con determinación y devolviendo cada intento de daño. Sus practicantes se plantan en el campo como pilares inamovibles, haciendo del aguante y la represalia su mayor virtud. <strong>Sus técnicas requieren \"Armas Corporales y Escudos\" para su ejecución.</strong>";
}

if ($estilo == 'ESPECIAL') {
    $clase_descripcion = "Los poderes especiales son un conjunto de habilidades muy unicas y particulares las cuales no encajan en un estilo o disciplina, ni forman parte del conjunto de poderes originados de las Akuma no Mi. Son una serie de poderes misticos y espirituales que forman parte del mundo en el que vivimos y simplemente existen sin una base cientifica, solo hay que creer en ellos, algunos los consideran leyendas, otros cuentos de hadas, pero estan ahí, en el mar...";
}

if ($estilo == 'KANPO KENPO') {
    $clase_descripcion = "Kanpo Kenpo es un estilo de combate que fusiona el conocimiento marcial sobre el cuerpo con el uso ancestral de la medicina herbaria oriental. Sus practicantes canalizan los efectos de extractos medicinales y tóxicos directamente en sus técnicas, alternando entre la sanación precisa y el debilitamiento estratégico del enemigo. Inspirado en la antigua filosofía del equilibrio natural, este estilo permite manipular el flujo vital del combate, curando o envenenando según la necesidad del momento, con movimientos elegantes y conocimiento profundo de plantas y esencias.";
}

if ($estilo == 'GLOBAL PERFORMER') {
    $clase_descripcion = "Los practicantes del Global Performer canalizan la energía elemental a través de su instrumento musical, generando ondas de choque, campos armónicos, distorsiones sensoriales o estímulos emocionales mediante su ejecución. Cada tipo de instrumento vibra con un elemento distinto, manifestando su afinidad natural y provocando efectos variados según el tipo de música interpretada. Este estilo se basa en la combinación de afinación emocional, expresión musical y resonancia elemental, haciendo de cada intérprete un canalizador de la fuerza natural del mundo.";
}

if ($estilo == 'HAKI') {
    $query_tecnicas2 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='BUSOSHOKU' ORDER BY CAST(tier AS UNSIGNED) ASC ");
    $query_tecnicas3 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='KENBUNSHOKU' ORDER BY CAST(tier AS UNSIGNED) ASC ");
    $query_tecnicas4 = $db->query(" SELECT * FROM mybb_op_tecnicas WHERE rama='HAOSHOKU' ORDER BY CAST(tier AS UNSIGNED) ASC ");

    $estilo2 = 'BUSOHOKU';
    $estilo3 = 'KENBUNSHOKU';
    $estilo4 = 'HAOSHOKU';

    $clase_descripcion = "El haki es una fuerza interior que todos los seres vivos poseen, aunque sólo algunos pocos logran despertar y aún menos dominar. Este poder se puede manifestar en tres formas, las dos primeras se pueden entrenar y desarrollar con esfuerzo que son la Armadura, capacidad para manifestar tu aura como una coraza; y la Observación, la capacidad de anticipar las intenciones de los demás. Y finalmente el tercer tipo de Haki, el cual es innato y solo uno de cada millon nace con él, el poder del conquistador.";
}

if ($estilo != '' && $estilo != 'HAKI') {
    $tecnicas_content = '';
    $is_staff = isset($mybb->user['usergroup']) && in_array($mybb->user['usergroup'], array(3, 4, 6));
    while ($tecnica = $db->fetch_array($query_tecnicas)) {
        // Filtrar técnicas de usuarios (tid que empiece por número)
        if (preg_match('/^[0-9]/', $tecnica['tid'])) {
            continue;
        }
        $tecnica['descripcion'] = nl2br($tecnica['descripcion']);
        $tecnica['tid'] = strtoupper($tecnica['tid']);
        $tecnica['clase'] = strtoupper($tecnica['clase']);
        $tecnicas_content .= create_technique_card($tecnica, $is_staff);
    }
    $tecnicas_templ = create_custom_spoiler($estilo, $tecnicas_content, get_technique_container_style());
}

if ($estilo2 != '') {
    $tecnicas_content2 = '';
    $is_staff = isset($mybb->user['usergroup']) && in_array($mybb->user['usergroup'], array(3, 4, 6));
    while ($tecnica = $db->fetch_array($query_tecnicas2)) {
        // Filtrar técnicas de usuarios (tid que empiece por número)
        if (preg_match('/^[0-9]/', $tecnica['tid'])) {
            continue;
        }
        $tecnica['descripcion'] = nl2br($tecnica['descripcion']);
        $tecnica['tid'] = strtoupper($tecnica['tid']);
        $tecnica['clase'] = strtoupper($tecnica['clase']);
        $tecnicas_content2 .= create_technique_card($tecnica, $is_staff);
    }
    $tecnicas_templ2 = create_custom_spoiler($estilo2, $tecnicas_content2, get_technique_container_style());
}

if ($estilo3 != '') {
    $tecnicas_content3 = '';
    $is_staff = isset($mybb->user['usergroup']) && in_array($mybb->user['usergroup'], array(3, 4, 6));
    while ($tecnica = $db->fetch_array($query_tecnicas3)) {
        // Filtrar técnicas de usuarios (tid que empiece por número)
        if (preg_match('/^[0-9]/', $tecnica['tid'])) {
            continue;
        }
        $tecnica['descripcion'] = nl2br($tecnica['descripcion']);
        $tecnica['tid'] = strtoupper($tecnica['tid']);
        $tecnica['clase'] = strtoupper($tecnica['clase']);
        $tecnicas_content3 .= create_technique_card($tecnica, $is_staff);
    }
    $tecnicas_templ3 = create_custom_spoiler($estilo3, $tecnicas_content3, get_technique_container_style());
}

if ($estilo4 != '') {
    $tecnicas_content4 = '';
    $is_staff = isset($mybb->user['usergroup']) && in_array($mybb->user['usergroup'], array(3, 4, 6));
    while ($tecnica = $db->fetch_array($query_tecnicas4)) {
        // Filtrar técnicas de usuarios (tid que empiece por número)
        if (preg_match('/^[0-9]/', $tecnica['tid'])) {
            continue;
        }
        $tecnica['descripcion'] = nl2br($tecnica['descripcion']);
        $tecnica['tid'] = strtoupper($tecnica['tid']);
        $tecnica['clase'] = strtoupper($tecnica['clase']);
        $tecnicas_content4 .= create_technique_card($tecnica, $is_staff);
    }
    $tecnicas_templ4 = create_custom_spoiler($estilo4, $tecnicas_content4, get_technique_container_style());
}


eval("\$page = \"".$templates->get("op_tecnicas_show")."\";");
output_page($page);


