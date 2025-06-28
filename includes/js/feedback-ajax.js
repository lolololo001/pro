document.addEventListener('DOMContentLoaded', function() {
    var feedbackForm = document.getElementById('feedbackForm');
    if (feedbackForm) {
        feedbackForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = this.querySelector('button[type="submit"]');
            btn.classList.add('loading');
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Submitting...';

            var form = e.target;
            var formData = new FormData(form);
            formData.append('submit_feedback', '1'); // Ensure PHP handler is triggered

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text()) // Expect HTML, not JSON!
            .then(html => {
                document.open();
                document.write(html);
                document.close();
            })
            .catch(error => {
                btn.classList.remove('loading');
                btn.innerHTML = '<i class=\'fas fa-paper-plane\'></i> Submit Feedback <span class=\'btn-hover-effect\'></span>';
                alert('Error: ' + error);
            });
        });
    }
}); 