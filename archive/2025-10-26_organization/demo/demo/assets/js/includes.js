/**
 * Simple include system for demo pages
 * Loads header and footer HTML files dynamically
 */

document.addEventListener('DOMContentLoaded', function() {
    // Load header
    fetch('includes/header.html')
        .then(response => response.text())
        .then(html => {
            document.body.insertAdjacentHTML('afterbegin', html);
            
            // After header is loaded, load footer
            return fetch('includes/footer.html');
        })
        .then(response => response.text())
        .then(html => {
            document.body.insertAdjacentHTML('beforeend', html);
        })
        .catch(error => {
            console.error('Error loading includes:', error);
        });
});
