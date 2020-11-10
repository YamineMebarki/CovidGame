/**
 * Created by yamine on 27/03/20.
 */
$(window).scroll(function() {
    if($(this).scrollTop() > 100) {
        $('nav').addClass('scrolled');
        $('a').addClass('text-white');
        $('span').addClass('text-black');
        $('nav').removeClass('navbar-light bg-light');
    } else {
        $('nav').addClass('navbar-light bg-light');
        $('nav').removeClass('scrolled');
        $('a').removeClass('text-white');
    }
});