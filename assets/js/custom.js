/*--------------------- Copyright (c) 2020 -----------------------
[Master Javascript]
Template Name: Tourism - Responsive HTML Template 
Version: 1.0.0
Author: PixelNx
-------------------------------------------------------------------*/
(function ($) {
	"use strict";
	/*-----------------------------------------------------
		Function  Start
	-----------------------------------------------------*/
		var Tourism = {
			initialised: false,
			version: 1.0,
			mobile: false,
			init: function () {
				if (!this.initialised) {
					this.initialised = true;
				} else {
					return;
				}
				/*-----------------------------------------------------
					Function Calling
				-----------------------------------------------------*/
				this.preLoader();
				this.searchBox();
				this.wowAnimation();
				this.navMenu();
				this.focusText();
				this.TeamSlider();
				this.partner();
				this.counter();
				this.topButton();
				this.niceSelectType();
				this.popupVideo();
				// this.popupVideoSlider();
				this.tourService();
				this.tourPackageSlider();
				this.tourTestimonial();
				this.quantity();
			},

			/*-----------------------------------------------------
				Fix Preloader
			-----------------------------------------------------*/
			preLoader: function () {
				$(window).on('load', function () {
					$(".preloader_wrapper").removeClass('preloader_active');
				});
				jQuery(window).on('load', function () {
					setTimeout(function () {
						jQuery('.preloader_open').addClass('loaded');
					}, 100);
				});
			},

			/*-----------------------------------------------------
				Fix Search Bar & Cart
			-----------------------------------------------------*/
			searchBox: function () {
				$('.searchBtn').on("click", function () {
					$('.searchBox').addClass('show');
				});
				$('.closeBtn').on("click", function () {
					$('.searchBox').removeClass('show');
				});
				$('.searchBox').on("click", function () {
					$('.searchBox').removeClass('show');
				});
				$(".search_bar_inner").on('click', function () {
					event.stopPropagation();
				});
			},

			/*-----------------------------------------------------
				Fix Animation 
			-----------------------------------------------------*/
			wowAnimation: function () {
				new WOW().init();
			},

			/*-----------------------------------------------------
				Fix Mobile Menu 
			-----------------------------------------------------*/
			navMenu: function () {
				$(document).ready(function(){
					$(document).on("click", function(event){
					  var $trigger = $(".menu_btn");
						if($trigger !== event.target && !$trigger.has(event.target).length){
							$("body").removeClass("open-toggle");
						}            
					});
					$(".menu_btn").click(function(){
					  $("body").toggleClass("open-toggle");
					});
				  });
				// var w = window.innerWidth;
				// if (w <= 991) {
				// 	$(".main_menu_wrapper>ul li").on('click', function () {
				// 		$(this).find('ul.sub_menu').slideToggle();
				// 		$(this).toggleClass("open");
				// 	});
				// 	$(".main_menu_wrapper>ul").on('click', function () {
				// 		e.stopPropagation();
				// 	});
				// 	$(".menu_btn").on('click', function (e) {
				// 		e.stopPropagation();
				// 		$(".main_menu_wrapper, .menu_btn_wrap").toggleClass("open");
				// 	});
				// 	$("body").on('click', function () {
				// 		$(".main_menu_wrapper, .menu_btn_wrap").removeClass("open");
				// 	});
				// }
			},

			/*-----------------------------------------------------
				Fix  On focus Placeholder
			-----------------------------------------------------*/
			focusText: function () {
				var place = '';
				$('input,textarea').focus(function () {
					place = $(this).attr('placeholder');
					$(this).attr('placeholder', '');
				}).blur(function () {
					$(this).attr('placeholder', place);
				});
			},
		
			/*-----------------------------------------------------
				Fix Team Slider 
			-----------------------------------------------------*/
			TeamSlider: function () {
				var TeamSwiper = new Swiper('.team_slider.swiper-container', {
					autoHeight: false,
					autoplay: true,
					spaceBetween: 30,
					slidesPerView: 4,
					loop: true,
					speed: 3000,
					autoplay: {
						delay: 3000,
					},
					centeredSlides: false,
					navigation: {
						nextEl: '.swiper-button-next1',
						prevEl: '.swiper-button-prev1',
					},
					pagination: {
						el: '.swiperPagination',
						clickable: true,
					},
					breakpoints: {
						0: {
							slidesPerView: 1,
							spaceBetween: 0,
						},
						575: {
							slidesPerView: 1,
							spaceBetween: 10,
						},
						767: {
							slidesPerView: 2,
							spaceBetween: 20,
						},
						992: {
							slidesPerView: 3,
							spaceBetween: 20,
						},
						1200: {
							slidesPerView: 4,
							spaceBetween: 30,
						},
					},
				});
			},			

			/*-----------------------------------------------------
				Fix Partner Slider 
			-----------------------------------------------------*/
			partner: function () {
				var PartnerSwiper = new Swiper('.partner_slider.swiper-container', {
					autoHeight: false,
					autoplay: true,
					spaceBetween: 30,
					slidesPerView: 8,
					loop: true,
					speed: 2000,
					autoplay: {
						delay: 1000,
					},
					breakpoints: {
						0: {
							slidesPerView: 2,
							spaceBetween: 0,
						},
						575: {
							slidesPerView: 2,
							spaceBetween: 10,
						},
						767: {
							slidesPerView: 4,
							spaceBetween: 20,
						},
						992: {
							slidesPerView: 6,
							spaceBetween: 20,
						},
						1200: {
							slidesPerView: 6,
							spaceBetween: 30,
						},
					},
				});
			},

			/*-----------------------------------------------------
				Fix Counter
			-----------------------------------------------------*/
			counter: function () {
				if($('.counter_holder').length > 0){
					var a = 0;
					$(window).scroll(function() {
						var topScroll = $('.counter_holder').offset().top - window.innerHeight;
						if (a == 0 && $(window).scrollTop() > topScroll) {
						$('.count_no').each(function() {
							var $this = $(this),
							countTo = $this.attr('data-count');
							$({
							countNum: $this.text()
							}).animate({
								countNum: countTo
							},
							{
							duration: 5000,
							easing: 'swing',
							step: function() {
								$this.text(Math.floor(this.countNum));
							},
							complete: function() {
								$this.text(this.countNum);
							}
							});
						});
						a = 1;
						}
					});
				};
			},

			/*-----------------------------------------------------
				Fix GoToTopButton
			-----------------------------------------------------*/
			topButton: function () {
				var scrollTop = $("#scroll");
				$(window).on('scroll', function () {
					if ($(this).scrollTop() < 500) {
						scrollTop.removeClass("active");
					} else {
						scrollTop.addClass("active");
					}
				});
				$('#scroll').click(function () {
					$("html, body").animate({
						scrollTop: 0
					}, 2000);
					return false;
				});

				$(function() {
					$('.go_to_demo').click (function() {
						$('html, body').animate({scrollTop: $('#go_to_demo').offset().top }, 'slow');
						return false;
					});
				});
			},

			/*-----------------------------------------------------
				Fix Select Field
			-----------------------------------------------------*/
			niceSelectType:function(){
				// if($('select').length > 0){
				// 	$('select').niceSelect();	
				// }
			},

			/*-----------------------------------------------------
				Fix Tour Service Slider 
			-----------------------------------------------------*/
			tourService: function () {
				var tourService = new Swiper('.tour_service.swiper-container', {
					autoHeight: false,
					autoplay: true,
					spaceBetween: 30,
					slidesPerView: 4,
					loop: true,
					speed: 3000,
					centeredSlides: false,
					autoplay: {
						delay: 2000,
					},
					navigation: {
						nextEl: '.next',
						prevEl: '.prev',
					},
					breakpoints: {
						0: {
							slidesPerView: 1,
							spaceBetween: 15,
						},
						575: {
							slidesPerView: 2,
							spaceBetween: 15,
						},
						767: {
							slidesPerView: 2,
							spaceBetween: 20,
						},
						768: {
							slidesPerView: 2,
							spaceBetween: 30,
						},
						992: {
							slidesPerView: 3,
							spaceBetween: 30,
						},
						1200: {
							slidesPerView: 4,
							spaceBetween: 30,
						},
						1920: {
							slidesPerView: 4,
							spaceBetween: 30,
						},
					},
				});
			},

			/*-----------------------------------------------------
				Fix Tour Video Popup
			-----------------------------------------------------*/
			popupVideo: function () {
				$('.popup-youtube').magnificPopup({
					type: 'iframe',
					iframe: {
						markup: '<div class="mfp-iframe-scaler">' +
							'<div class="mfp-close"></div>' +
							'<iframe class="mfp-iframe" frameborder="0" allowfullscreen></iframe>' +
							'<div class="mfp-title">Some caption</div>' +
							'</div>',
						patterns: {
							youtube: {
								index: 'youtube.com/',
								id: 'v=',
								src: 'https://youtu.be/AFwMLBBjk7Y'
							}
						}
					},
					vimeo: {
						index: 'vimeo.com/',
						id: '/',
						src: '//player.vimeo.com/video/%id%?autoplay=1'
					  },
					  gmaps: {
						index: '//maps.google.',
						src: '%id%&output=embed'
					  }
				});
			},
			/*-----------------------------------------------------
				Fix Tour Package Slider 
			-----------------------------------------------------*/
			tourPackageSlider: function () {
				var tourPackageSlider = new Swiper('.tour_package.swiper-container', {
					autoHeight: false,
					autoplay: true,
					spaceBetween: 30,
					slidesPerView: 4,
					loop: true,
					speed: 3000,
					centeredSlides: false,
					autoplay: {
						delay: 2000,
					},
					pagination: {
						el: '.bullets',
						clickable: true,
					},
					breakpoints: {
						0: {
							slidesPerView: 1,
							spaceBetween: 15,
						},
						575: {
							slidesPerView: 2,
							spaceBetween: 15,
						},
						767: {
							slidesPerView: 2,
							spaceBetween: 20,
						},
						768: {
							slidesPerView: 2,
							spaceBetween: 30,
						},
						992: {
							slidesPerView: 2,
							spaceBetween: 30,
						},
						1200: {
							slidesPerView: 3,
							spaceBetween: 30,
						},
						1920: {
							slidesPerView: 4,
							spaceBetween: 30,
						},
					},
				});
			},

			/*-----------------------------------------------------
				Fix Tour Testimonial Slider 
			-----------------------------------------------------*/
			tourTestimonial: function () {
				var tourTestimonial = new Swiper('.tourTestimonial.swiper-container', {
					autoHeight: false,
					autoplay: true,
					spaceBetween: 0,
					slidesPerView: 1,
					effect: 'fade',
					loop: true,
					speed: 1500,
					centeredSlides: false,
					autoplay: {
						delay: 1000,
					},
					navigation: {
						nextEl: '.testNext',
						prevEl: '.testPrev',
					},
				});
			},
			quantity:function(){
                var quantity=0;
				$('.quantity-plus').on('click', function(e){
					e.preventDefault();
					var quantity = Number($(this).siblings('.quantity').val());
					$(this).siblings('.quantity').val(quantity + 1);            

				});
				$('.quantity-minus').on('click', function(e){
					e.preventDefault();
					var quantity = Number($(this).siblings('.quantity').val());
					if(quantity>0){
						$(this).siblings('.quantity').val(quantity - 1);
					}
				});	
            },
			
		};

		Tourism.init();

})(jQuery);

/*-----------------------------------------------------
	Fix Contact Form Submission
-----------------------------------------------------*/
// Contact Form Submission
function checkRequire(formId , targetResp){
    targetResp.html('');
    var email = /^([\w-]+(?:\.[\w-]+)*)@((?:[\w-]+\.)*\w[\w-]{0,66})\.([a-z]{2,6}(?:\.[a-z]{2})?)$/;
    var url = /(http|ftp|https):\/\/[\w-]+(\.[\w-]+)+([\w.,@?^=%&amp;:\/~+#-]*[\w@?^=%&amp;\/~+#-])?/;
    var image = /\.(jpe?g|gif|png|PNG|JPE?G)$/;
    var mobile = /^[\s()+-]*([0-9][\s()+-]*){6,20}$/;
    var facebook = /^(https?:\/\/)?(www\.)?facebook.com\/[a-zA-Z0-9(\.\?)?]/;
    var twitter = /^(https?:\/\/)?(www\.)?twitter.com\/[a-zA-Z0-9(\.\?)?]/;
    var google_plus = /^(https?:\/\/)?(www\.)?plus.google.com\/[a-zA-Z0-9(\.\?)?]/;
    var check = 0;
    $('#er_msg').remove();
    var target = (typeof formId == 'object')? $(formId):$('#'+formId);
    target.find('input , textarea , select').each(function(){
        if($(this).hasClass('require')){
            if($(this).val().trim() == ''){
                check = 1;
                $(this).focus();
                $(this).parent('div').addClass('form_error');
                targetResp.html('You missed out some fields.');
                $(this).addClass('error');
                return false;
            }else{
                $(this).removeClass('error');
                $(this).parent('div').removeClass('form_error');
            }
        }
        if($(this).val().trim() != ''){
            var valid = $(this).attr('data-valid');
            if(typeof valid != 'undefined'){
                if(!eval(valid).test($(this).val().trim())){
                    $(this).addClass('error');
                    $(this).focus();
                    check = 1;
                    targetResp.html($(this).attr('data-error'));
                    return false;
                }else{
                    $(this).removeClass('error');
                }
            }
        }
    });
    return check;
}
$(".submitForm").on('click', function() {
    var _this = $(this);
    var targetForm = _this.closest('form');
    var errroTarget = targetForm.find('.response');
    var check = checkRequire(targetForm , errroTarget);
    
    if(check == 0){
       var formDetail = new FormData(targetForm[0]);
        formDetail.append('form_type' , _this.attr('form-type'));
        $.ajax({
            method : 'post',
            url : 'ajaxmail.php',
            data:formDetail,
            cache:false,
            contentType: false,
            processData: false
        }).done(function(resp){
            console.log(resp);
            if(resp == 1){
                targetForm.find('input').val('');
                targetForm.find('textarea').val('');
                errroTarget.html('<p style="color:green;">Mail has been sent successfully.</p>');
            }else{
                errroTarget.html('<p style="color:red;">Something went wrong please try again latter.</p>');
            }
        });
    }
});

$("#deatination_drop").select2({closeOnSelect:true,width:'re  	solve'});
$(".defult-select-drowpown").select2({closeOnSelect:true,});
// jQuery('#datepicker').datepicker({format:'dd-mm-yyyy',startDate:'+1d'});
var destinationSliderTwo=new Swiper('.tor_destination-slider-two',{
	slidesPerView:1,
	speed:1000,
	spaceBetween:24,
	loop:true,
	spped: 3000,
	roundLengths:true,
	autoplay:{
		delay:1500
	},
	pagination:	{
		el:".testi-pagination",
		clickable:true
	},
	breakpoints:{
		0:{
			slidesPerView:1
		},
		375:{
			slidesPerView:2
		},
		768:{
			slidesPerView:3
		},
		992:{
			slidesPerView:3
		},
		1200:{
			slidesPerView:4
		},
	}
});
if($("#tor-gallery-popup").length > 0){
	$(document).ready(function () {
	  $('.tor-gallery-popup-wrapper, .popup-gallery1, .popup-gallery2, .popup-gallery3').magnificPopup({
		delegate: 'a',
		type: 'image',
		tLoading: 'Loading image #%curr%...',
		mainClass: 'mfp-img-mobile',
		gallery: {
		  enabled: true,
		  navigateByImgClick: true,
		  preload: [0, 1] // Will preload 0 - before current, and 1 after the current image
		},
		image: {
		  tError: '<a href="%url%">The image #%curr%</a> could not be loaded.',
		  titleSrc: function (item) {
			return item.el.attr('title') + '<small></small>';
		  }
		}
	  });
	  });
	}
function show1(){
	document.getElementById('triphalf-show').style.display ='block';
	document.getElementById('tripful-show').style.display ='none';
  }
function show2(){
	document.getElementById('triphalf-show').style.display ='none';
	document.getElementById('tripful-show').style.display ='block';
  }
function fl_show1(){
	document.getElementById('fl-triphalf-show').style.display ='block';
	document.getElementById('fl-tripful-show').style.display ='none';
  }
function fl_show2(){
	document.getElementById('fl-triphalf-show').style.display ='none';
	document.getElementById('fl-tripful-show').style.display ='block';
  }
function tr_show1(){
	document.getElementById('tr-triphalf-show').style.display ='block';
	document.getElementById('tr-tripful-show').style.display ='none';
  }
function tr_show2(){
	document.getElementById('tr-triphalf-show').style.display ='none';
	document.getElementById('tr-tripful-show').style.display ='block';
  }
function bus_show1(){
	document.getElementById('bus-triphalf-show').style.display ='block';
	document.getElementById('bus-tripful-show').style.display ='none';
  }
function bus_show2(){
	document.getElementById('bus-triphalf-show').style.display ='none';
	document.getElementById('bus-tripful-show').style.display ='block';
  }
function ship_show1(){
	document.getElementById('ship-triphalf-show').style.display ='block';
	document.getElementById('ship-tripful-show').style.display ='none';
  }
function ship_show2(){
	document.getElementById('ship-triphalf-show').style.display ='none';
	document.getElementById('ship-tripful-show').style.display ='block';
  }
  $( function() {
	$( "#datepicker" ).datepicker();
  } );


  // Get the current year
const currentYear = new Date().getFullYear();
// Display the current year in the span with the ID 'current-year'
document.getElementById("current-year").textContent = currentYear;