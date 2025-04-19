document.addEventListener('DOMContentLoaded', () => {
  // Toggle navbar burger menu
  const burgers = Array.from(document.querySelectorAll('.navbar-burger'));
  burgers.forEach(burger => {
    burger.addEventListener('click', () => {
      const targetId = burger.dataset.target;
      const target = document.getElementById(targetId);
      burger.classList.toggle('is-active');
      target.classList.toggle('is-active');
    });
  });

  // Dropdown toggle with outside click to close
  const dropdowns = Array.from(document.querySelectorAll('.dropdown'));

  dropdowns.forEach(dropdown => {
    const trigger = dropdown.querySelector('.dropdown-trigger');

    if (trigger) {
      trigger.addEventListener('click', (e) => {
        e.stopPropagation(); // Prevent click from reaching the document
        dropdown.classList.toggle('is-active');
      });
    }
  });

  // Close dropdowns on outside click
  document.addEventListener('click', () => {
    dropdowns.forEach(dropdown => {
      dropdown.classList.remove('is-active');
    });
  });
});
