document.addEventListener('DOMContentLoaded', () => {
  const tableHeaders = document.querySelectorAll('th[data-sort]');
  tableHeaders.forEach((header) => {
    header.addEventListener('click', () => {
      const table = header.closest('table');
      const index = [...header.parentElement.children].indexOf(header);
      const rows = [...table.querySelectorAll('tbody tr')];
      const asc = !header.classList.contains('asc');

      rows.sort((a, b) => {
        const aText = a.children[index].innerText.trim().toLowerCase();
        const bText = b.children[index].innerText.trim().toLowerCase();
        return asc ? aText.localeCompare(bText) : bText.localeCompare(aText);
      });

      table.querySelector('tbody').innerHTML = '';
      rows.forEach((row) => table.querySelector('tbody').appendChild(row));
      tableHeaders.forEach((h) => h.classList.remove('asc', 'desc'));
      header.classList.add(asc ? 'asc' : 'desc');
    });
  });
});
