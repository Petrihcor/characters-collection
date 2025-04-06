/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('.edit-toggle').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const formId = this.getAttribute('data-target');
            const form = document.getElementById(formId);
            form.style.display = (form.style.display === 'block') ? 'none' : 'block';
        });
    });
});