document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('table[data-sortable]').forEach((table) => {
    const headers = table.querySelectorAll('th[data-sort]');
    headers.forEach((header, idx) => {
      header.addEventListener('click', () => {
        const body = table.querySelector('tbody');
        if (!body) return;

        const rows = Array.from(body.querySelectorAll('tr'));
        const dir = header.dataset.dir === 'asc' ? 'desc' : 'asc';
        const type = header.dataset.sort || 'text';

        rows.sort((rowA, rowB) => {
          const a = rowA.children[idx]?.innerText.trim() || '';
          const b = rowB.children[idx]?.innerText.trim() || '';
          if (type === 'date') {
            return dir === 'asc' ? new Date(a) - new Date(b) : new Date(b) - new Date(a);
          }
          return dir === 'asc' ? a.localeCompare(b) : b.localeCompare(a);
        });

        headers.forEach((h) => {
          h.dataset.dir = '';
          h.classList.remove('asc', 'desc');
        });

        header.dataset.dir = dir;
        header.classList.add(dir);
        rows.forEach((row) => body.appendChild(row));
      });
    });
  });
});
