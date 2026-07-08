/**
 * ficha/pestanas.js — Navegación de pestañas y rasgos
 * Requiere: core.js (FICHA namespace)
 */
(function (FICHA, w) {

  function clickPestana(tipo) {
    $('.pestana').removeClass('pestana-active');
    sessionStorage.setItem('lastFichaTab', tipo);

    $('#portada').css('display', 'none');
    $('#biografia').css('display', 'none');
    $('#belico').css('display', 'none');
    $('#tecnicass').css('display', 'none');
    $('#inventario').css('display', 'none');
    $('#secreto1').css('display', 'none');

    $('.nombre').css({ 'text-decoration': 'none', 'background-color': 'transparent', 'color': '' });
    $('.apodo').css({ 'text-decoration': 'none', 'background-color': 'transparent', 'color': faccionColor });

    if (tipo === 'portada') {
      $('#portada').css('display', 'block');
      $('.fondo-ficha').css('background-image', 'url(/images/op/uploads/Fondo' + faccion + '1_One_Piece_Gaiden_Foro_Rol.webp)');
      $('#pestana_portada').addClass('pestana-active');
    }
    if (tipo === 'biografia') {
      $('#biografia').css('display', 'block');
      $('.fondo-ficha').css('background-image', 'url(/images/op/uploads/Fondo' + faccion + '2_One_Piece_Gaiden_Foro_Rol.webp)');
      $('#pestana_biografia').addClass('pestana-active');
    }
    if (tipo === 'belico') {
      $('#belico').css('display', 'block');
      $('.fondo-ficha').css('background-image', 'url(/images/op/uploads/Fondo' + faccion + '3_One_Piece_Gaiden_Foro_Rol.webp)');
      $('#pestana_belico').addClass('pestana-active');
    }
    if (tipo === 'tecnicass') {
      $('#tecnicass').css('display', 'block');
      $('.fondo-ficha').css('background-image', 'url(/images/op/uploads/Fondo' + faccion + '4_One_Piece_Gaiden_Foro_Rol.webp)');
      $('#pestana_tecnicas').addClass('pestana-active');
    }
    if (tipo === 'inventario') {
      $('#inventario').css('display', 'block');
      $('.fondo-ficha').css('background-image', 'url(/images/op/uploads/Fondo' + faccion + '5_One_Piece_Gaiden_Foro_Rol.webp)');
      $('#pestana_inventario').addClass('pestana-active');
    }
    if (tipo === 'secreto1') {
      $('#secreto1').css('display', 'block');
      $('.fondo-ficha').css('background-image', 'url(/images/op/uploads/Fondo' + faccion + 'Secret_One_Piece_Gaiden_Foro_Rol.webp)');
      $('#pestana_secreto1').addClass('pestana-active');
      $('.nombre').css({
        'text-decoration': 'line-through',
        'text-decoration-color': 'black',
        'text-decoration-thickness': '4px',
        'background-color': 'black',
        'color': 'black'
      });
      $('.apodo').css({
        'text-decoration': 'line-through',
        'text-decoration-color': 'black',
        'text-decoration-thickness': '4px',
        'background-color': 'black',
        'color': 'black'
      });
    }
  }

  function clickRasgos(tipo) {
    $('.rasgos_box').removeClass('rasgos-active');
    if (tipo === 'virtudes') {
      $('#rasgos_virtudes').css('display', 'block');
      $('#rasgos_defectos').css('display', 'none');
      $('#virtudes_box').addClass('rasgos-active');
    }
    if (tipo === 'defectos') {
      $('#rasgos_virtudes').css('display', 'none');
      $('#rasgos_defectos').css('display', 'block');
      $('#defectos_box').addClass('rasgos-active');
    }
  }

  w.clickPestana = clickPestana;
  w.clickRasgos  = clickRasgos;

})(window.FICHA, window);
