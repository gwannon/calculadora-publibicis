var cpstep = 1;

jQuery(".cp-step button.next").click(function(e) {
  e.preventDefault();
  jQuery(this).parent().removeClass("current");
  jQuery(this).parent().next().addClass("current");
  cpstep++;
  jQuery("form#cp-form > div#counter > img").attr("src", "/wp-content/plugins/calculadora-publibicis/images/"+cpstep+".svg");
});