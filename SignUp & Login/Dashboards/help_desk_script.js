// Modal functionality
document.addEventListener('DOMContentLoaded', function() {
    const createTicketBtn = document.getElementById('createTicketBtn');
    const createTicketModal = document.getElementById('createTicketModal');
    const viewTicketModal = document.getElementById('viewTicketModal');
    const closeButtons = document.querySelectorAll('.close');

    createTicketBtn.addEventListener('click', () => {
        createTicketModal.style.display = 'block';
    });

    closeButtons.forEach(button => {
        button.addEventListener('click', () => {
            createTicketModal.style.display = 'none';
            viewTicketModal.style.display = 'none';
        });
    });

    window.addEventListener('click', (event) => {
        if (event.target === createTicketModal) {
            createTicketModal.style.display = 'none';
        }
        if (event.target === viewTicketModal) {
            viewTicketModal.style.display = 'none';
        }
    });

    // View ticket functionality
    const viewButtons = document.querySelectorAll('.view-btn');
    viewButtons.forEach(button => {
        button.addEventListener('click', () => {
            const ticketId = button.getAttribute('data-id');
            fetchTicketDetails(ticketId, 'view');
        });
    });

    // Print ticket functionality
    const printButtons = document.querySelectorAll('.print-btn');
    printButtons.forEach(button => {
        button.addEventListener('click', () => {
            const ticketId = button.getAttribute('data-id');
            fetchTicketDetails(ticketId, 'print');
        });
    });

    // Alert message auto-hide
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(() => alert.style.display = 'none', 500);
        }, 3000);
    });
});

function fetchTicketDetails(ticketId, action) {
    // Use AJAX to fetch the ticket details
    fetch(`index.php?view_ticket=${ticketId}`)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            if (action === 'view') {
                // Extract ticket details from the HTML response
                const ticketDetailElement = doc.querySelector('#ticketDetails');
                if (ticketDetailElement) {
                    document.getElementById('ticketDetails').innerHTML = ticketDetailElement.innerHTML;
                    document.getElementById('viewTicketModal').style.display = 'block';
                }
            } else if (action === 'print') {
                // Extract print layout from the HTML response
                const printLayoutElement = doc.querySelector('#printLayout');
                if (printLayoutElement) {
                    document.getElementById('printLayout').innerHTML = printLayoutElement.innerHTML;
                    window.print();
                }
            }
        })
        .catch(error => console.error('Error fetching ticket details:', error));
}