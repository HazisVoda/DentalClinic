const navbar = document.querySelector(".navbar");

  function makeNavbarSticky() {
    if (window.pageYOffset > navbar.offsetTop) {
      navbar.classList.add("sticky");
    } else {
      navbar.classList.remove("sticky");
    }
  }

  window.addEventListener("scroll", makeNavbarSticky);

const menu = document.querySelector('#mobile-menu');
const menuLinks = document.querySelector('.navbar__menu');
const navLogo = document.querySelector('#navbar__logo');
const body = document.querySelector('body');

//display mobile menu
const mobileMenu = () =>{
    menu.classList.toggle('is-active');
    menuLinks.classList.toggle('active');
    body.classList.toggle('active');
};

menu.addEventListener('click', mobileMenu);

//show active menu while scrolling
const highlightMenu = () =>{
    const elem = document.querySelector('.highlight')
    const homeMenu = document.querySelector('#home-page')
    const serviceMenu = document.querySelector('#services')
    const trainersMenu = document.querySelector('#Staff')
    let scrollPos = window.scrollY

    if(window.innerWidth > 960 && scrollPos < 600) {
        homeMenu.classList.add('hightlight')
        aboutMenu.classList.remove ('highlight')
    }
}

//animation
gsap.registerPlugin(ScrollTrigger)

gsap.from('.animate-hero', {
    duration: 0.6,
    opacity: 0,
    y: -150,
    stagger: 0.3
});

gsap.from('.animate-services', {
    scrollTrigger:'.animate-services',
    duration: 0.5,
    opacity: 1,
    x: -150,
    stagger: 0.12,
});

gsap.from('.animate-img', {
    scrollTrigger:'.animate-services',
    duration: 1.2,
    opacity: 0,
    x: -200,
});

gsap.from('.animate-membership', {
    scrollTrigger:'.animate-membership',
    duration: 1,
    opacity: 0,
    y: -150,
    stagger: 0.3,
    delay: 0.5
});

gsap.from('.animate-card', {
    scrollTrigger:'.animate-card',
    duration: 1,
    opacity: 0,
    y: -150,
    stagger: 0.1,
    delay: 0.2
});

gsap.from('.animate-team', {
    scrollTrigger:'.animate-team',
    duration: 1,
    opacity: 0,
    y: -150,
    stagger: 0.3,
    delay: 0.2
});

gsap.from('.animate-marova',{
scrollTrigger:'.animate-marova',
    duration: 0.6,
    opacity: 0,
    y: -150,
    stagger: 0.3
});

gsap.from('.animate-add', {
    scrollTrigger:'.animate-add',
    duration: 0.8,
    opacity: 1,
    x: -150,
    stagger: 0.19,
});

gsap.from('.animate-box', {
    scrollTrigger:'.animate-box',
    duration: 1,
    opacity: 0,
    y: -150,
    stagger: 0.1,
    delay: 0.2
});

gsap.from('.animate-fjal', {
    scrollTrigger:'.animate-fjal',
    duration: 1,
    opacity: 0,
    y: -150,
    stagger: 0.1,
    delay: 0.5
});

gsap.from('.animate-email', {
    scrollTrigger:'.animate-email',
    duration: 1,
    opacity: 0,
    y: -150,
    stagger: 0.25,
    delay: 0.5
});


