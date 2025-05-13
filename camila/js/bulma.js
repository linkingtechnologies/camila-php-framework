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
  

  // Open modal when a trigger button is clicked
  document.querySelectorAll('.open-modal').forEach(button => {
    button.addEventListener('click', () => {
      const modalId = button.getAttribute('data-target');
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.classList.add('is-active');
      }
    });
  });

  // Close modal when clicking background, top-right 'X', or footer button
  document.querySelectorAll('.bulma-modal').forEach(modal => {
    const closeElements = modal.querySelectorAll('.modal-background, .modal-close, .modal-close-btn');
    closeElements.forEach(el => {
      el.addEventListener('click', () => {
        modal.classList.remove('is-active');
      });
    });
  });
  

});
