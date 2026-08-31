/**
 * LASU FCIT CMS — Landing Page Interactions & Animations
 * Lightweight, no external libraries.
 * Respects prefers-reduced-motion.
 */

(function () {
  'use strict';

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const isTouchDevice = () => ('ontouchstart' in window) || navigator.maxTouchPoints > 0;

  /* ============================================================
     1. NAVBAR — Scroll glassmorphism transition
     ============================================================ */
  (function initNavbar() {
    const header = document.querySelector('header');
    if (!header) return;
    header.style.transition = 'background 0.35s ease, box-shadow 0.35s ease, border-color 0.35s ease, backdrop-filter 0.35s ease';

    function onScroll() {
      if (window.scrollY > 40) {
        header.classList.add('navbar-scrolled');
      } else {
        header.classList.remove('navbar-scrolled');
      }
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  })();


  /* ============================================================
     2. SCROLL REVEAL — IntersectionObserver
     ============================================================ */
  (function initScrollReveal() {
    if (prefersReducedMotion) {
      // Just show everything immediately
      document.querySelectorAll('.reveal').forEach(el => el.classList.add('is-visible'));
      document.querySelectorAll('.section-heading-line').forEach(el => el.classList.add('is-visible'));
      return;
    }

    const revealEls = document.querySelectorAll('.reveal');
    const headingLines = document.querySelectorAll('.section-heading-line');

    if (!revealEls.length && !headingLines.length) return;

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    revealEls.forEach(el => observer.observe(el));
    headingLines.forEach(el => observer.observe(el));
  })();


  /* ============================================================
     3. ANIMATED NUMBER COUNTERS
     ============================================================ */
  (function initCounters() {
    const counters = document.querySelectorAll('[data-count-to]');
    if (!counters.length) return;

    function animateCounter(el) {
      const target = parseInt(el.getAttribute('data-count-to'), 10);
      if (isNaN(target) || target === 0) { el.textContent = '0'; return; }

      if (prefersReducedMotion) { el.textContent = target; return; }

      const duration = Math.min(1800, Math.max(600, target * 60));
      const startTime = performance.now();
      const startVal = 0;

      function step(now) {
        const elapsed = now - startTime;
        const progress = Math.min(elapsed / duration, 1);
        // ease-out cubic
        const eased = 1 - Math.pow(1 - progress, 3);
        const current = Math.round(startVal + (target - startVal) * eased);
        el.textContent = current;
        if (progress < 1) requestAnimationFrame(step);
        else el.textContent = target;
      }
      requestAnimationFrame(step);
    }

    const counterObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          counterObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });

    counters.forEach(el => counterObserver.observe(el));
  })();


  /* ============================================================
     4. WORKFLOW TIMELINE LINE FILL
     ============================================================ */
  (function initWorkflowLine() {
    const fillEl = document.querySelector('.workflow-line-fill');
    if (!fillEl) return;

    if (prefersReducedMotion) { fillEl.classList.add('is-filled'); return; }

    const section = document.querySelector('#how-it-works');
    if (!section) return;

    const lineObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          setTimeout(() => fillEl.classList.add('is-filled'), 200);
          lineObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });
    lineObserver.observe(section);
  })();


  /* ============================================================
     5. DEPARTMENT CARD — Cursor Spotlight
     ============================================================ */
  (function initSpotlight() {
    if (isTouchDevice() || prefersReducedMotion) return;

    const cards = document.querySelectorAll('.dept-card');
    cards.forEach(card => {
      card.addEventListener('mousemove', (e) => {
        const rect = card.getBoundingClientRect();
        const x = ((e.clientX - rect.left) / rect.width) * 100;
        const y = ((e.clientY - rect.top) / rect.height) * 100;
        const spotlight = card.querySelector('.card-spotlight');
        if (spotlight) {
          spotlight.style.setProperty('--mx', `${x}%`);
          spotlight.style.setProperty('--my', `${y}%`);
        }
      });
    });
  })();


  /* ============================================================
     6. FEATURE CARDS — Subtle 3D tilt
     ============================================================ */
  (function initCardTilt() {
    if (isTouchDevice() || prefersReducedMotion) return;

    const cards = document.querySelectorAll('.feature-card');
    cards.forEach(card => {
      card.addEventListener('mousemove', (e) => {
        const rect = card.getBoundingClientRect();
        const cx = rect.left + rect.width / 2;
        const cy = rect.top + rect.height / 2;
        const dx = (e.clientX - cx) / (rect.width / 2);
        const dy = (e.clientY - cy) / (rect.height / 2);
        // Max ±5deg tilt
        const rotateX = -dy * 4;
        const rotateY = dx * 4;
        card.style.transform = `perspective(800px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-6px)`;
      });
      card.addEventListener('mouseleave', () => {
        card.style.transform = '';
      });
    });
  })();


  /* ============================================================
     7. PARALLAX — Hero decorative elements on scroll
     ============================================================ */
  (function initParallax() {
    if (isTouchDevice() || prefersReducedMotion) return;

    const shapes = document.querySelectorAll('.parallax-shape');
    if (!shapes.length) return;

    let ticking = false;
    window.addEventListener('scroll', () => {
      if (ticking) return;
      requestAnimationFrame(() => {
        const scrollY = window.scrollY;
        shapes.forEach(shape => {
          const speed = parseFloat(shape.getAttribute('data-parallax-speed') || '0.2');
          shape.style.transform = `translateY(${scrollY * speed}px)`;
        });
        ticking = false;
      });
      ticking = true;
    }, { passive: true });
  })();


  /* ============================================================
     8. STAGGERED CARD REVEAL — grouped delays
     ============================================================ */
  (function initStaggeredReveal() {
    if (prefersReducedMotion) return;

    // Apply stagger to groups of .reveal-stagger-group children
    document.querySelectorAll('.reveal-stagger-group').forEach(group => {
      const items = group.querySelectorAll('.reveal');
      items.forEach((item, i) => {
        item.style.transitionDelay = `${i * 0.10}s`;
      });
    });
  })();


  /* ============================================================
     9. SMOOTH SCROLL for anchor links
     ============================================================ */
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', (e) => {
      const target = document.querySelector(a.getAttribute('href'));
      if (!target) return;
      e.preventDefault();
      target.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth' });
    });
  });

})();
