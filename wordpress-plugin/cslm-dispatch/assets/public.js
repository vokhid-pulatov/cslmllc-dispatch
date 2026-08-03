(() => {
  document.addEventListener('click', event => {
    const button = event.target.closest('.reveal-email');
    if (!button || button.dataset.revealed) return;
    const address = `${button.dataset.user}@${button.dataset.domain}`;
    const link = document.createElement('a');
    link.href = `mailto:${address}`;
    link.textContent = address;
    link.className = 'revealed-email';
    button.dataset.revealed = '1';
    button.replaceWith(link);
  });
})();
