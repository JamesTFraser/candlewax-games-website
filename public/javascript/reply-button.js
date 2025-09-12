document.querySelectorAll('.reply-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
        e.preventDefault();
        const form = document.getElementById('reply-form');
        form.style.display = 'block';
        document.getElementById('parent-id').value = e.target.dataset.parentId;
        btn.closest('.row').nextElementSibling.after(form);
    });
});