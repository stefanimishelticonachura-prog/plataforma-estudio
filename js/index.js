// ===== CARRUSEL DE IMÁGENES =====
document.addEventListener('DOMContentLoaded', function() {
    const slides = document.querySelectorAll('.carousel-slide');
    const indicators = document.querySelectorAll('.indicator');
    const prevBtn = document.querySelector('.prev-btn');
    const nextBtn = document.querySelector('.next-btn');
    let currentSlide = 0;
    let slideInterval;

    // Función para mostrar un slide específico
    function goToSlide(index) {
        // Remover clase active de todos los slides
        slides.forEach(slide => slide.classList.remove('active'));
        indicators.forEach(ind => ind.classList.remove('active'));

        // Añadir clase active al slide seleccionado
        slides[index].classList.add('active');
        indicators[index].classList.add('active');

        // Actualizar slide actual
        currentSlide = index;
    }

    // Función para ir al siguiente slide
    function nextSlide() {
        const nextIndex = (currentSlide + 1) % slides.length;
        goToSlide(nextIndex);
    }

    // Función para ir al slide anterior
    function prevSlide() {
        const prevIndex = (currentSlide - 1 + slides.length) % slides.length;
        goToSlide(prevIndex);
    }

    // Función para iniciar el auto-slide
    function startAutoSlide() {
        if (slideInterval) {
            clearInterval(slideInterval);
        }
        slideInterval = setInterval(nextSlide, 10000); // Cambia cada 10 segundos
    }

    // Función para detener el auto-slide
    function stopAutoSlide() {
        if (slideInterval) {
            clearInterval(slideInterval);
            slideInterval = null;
        }
    }

    // Event listeners para los botones
    prevBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        prevSlide();
        // Reiniciar el temporizador cuando el usuario interactúa
        stopAutoSlide();
        startAutoSlide();
    });

    nextBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        nextSlide();
        // Reiniciar el temporizador cuando el usuario interactúa
        stopAutoSlide();
        startAutoSlide();
    });

    // Event listeners para los indicadores
    indicators.forEach((indicator, index) => {
        indicator.addEventListener('click', function(e) {
            e.stopPropagation();
            goToSlide(index);
            // Reiniciar el temporizador cuando el usuario interactúa
            stopAutoSlide();
            startAutoSlide();
        });
    });

    // Pausar el auto-slide cuando el mouse está sobre el carrusel
    const carouselContainer = document.querySelector('.carousel-container');
    carouselContainer.addEventListener('mouseenter', function() {
        stopAutoSlide();
    });

    carouselContainer.addEventListener('mouseleave', function() {
        startAutoSlide();
    });

    // Iniciar el carrusel
    goToSlide(0);
    startAutoSlide();

    // Soporte para teclado (flechas izquierda/derecha)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowLeft') {
            prevSlide();
            stopAutoSlide();
            startAutoSlide();
        } else if (e.key === 'ArrowRight') {
            nextSlide();
            stopAutoSlide();
            startAutoSlide();
        }
    });
});     