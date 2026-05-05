// test.js — K&B Group Landing Page

// Init Lucide icons
lucide.createIcons();

// Footer year
document.getElementById('yr').textContent = new Date().getFullYear();

// Mobile nav
const burger = document.getElementById('burger');
const mobileNav = document.getElementById('mobileNav');
burger.addEventListener('click', () => mobileNav.classList.toggle('open'));

// Hero tabs
document.querySelectorAll('.tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('tab--active'));
    tab.classList.add('tab--active');
  });
});

// Animate bar chart on scroll
const bars = document.querySelectorAll('.bar-fill');
bars.forEach(b => { b.style.height = '0'; });

const chartObserver = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      const target = entry.target;
      const finalH = target.style.getPropertyValue('--h') || target.dataset.h;
      target.style.transition = 'height .7s cubic-bezier(.4,0,.2,1)';
      target.style.height = finalH;
      chartObserver.unobserve(target);
    }
  });
}, { threshold: 0.3 });

bars.forEach(b => chartObserver.observe(b));

// Fade-in on scroll for cards
const fadeTargets = document.querySelectorAll(
  '.service-card, .benefits__list li, .pricing-card, .stat-card, .diagram__node'
);
fadeTargets.forEach((el, i) => {
  el.style.opacity = '0';
  el.style.transform = 'translateY(16px)';
  el.style.transition = `opacity .45s ease ${i * 50}ms, transform .45s ease ${i * 50}ms`;
});

const fadeObserver = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.style.opacity = '1';
      entry.target.style.transform = 'translateY(0)';
      fadeObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.1 });

fadeTargets.forEach(el => fadeObserver.observe(el));
