(() => {
  document.documentElement.classList.add('js');

  const rows = document.querySelectorAll('.item-row, .group, .site-header');
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting) {
          entry.target.classList.add('in-view');
          io.unobserve(entry.target);
        }
      }
    }, { threshold: 0.08 });
    rows.forEach((el) => io.observe(el));
  }

  const copyHints = document.querySelectorAll('[data-copy]');
  copyHints.forEach((btn) => {
    btn.addEventListener('click', async () => {
      const text = btn.getAttribute('data-copy') || '';
      try {
        await navigator.clipboard.writeText(text);
        const prev = btn.textContent;
        btn.textContent = 'Copied';
        setTimeout(() => { btn.textContent = prev; }, 1200);
      } catch (_err) {
        // Clipboard may be blocked; leave UI unchanged.
      }
    });
  });
})();
