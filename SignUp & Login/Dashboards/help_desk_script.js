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
    if (action === 'view') {
        // Show the modal first
        document.getElementById('viewTicketModal').style.display = 'block';
        document.getElementById('ticketDetails').innerHTML = '<div class="loading">Loading ticket details...</div>';

        // Fetch the ticket details
        fetch(`ticket_ajax.php?action=get_ticket_details&ticket_id=${ticketId}`)
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    document.getElementById('ticketDetails').innerHTML = data.html;
                } else {
                    document.getElementById('ticketDetails').innerHTML =
                        `<div class="error">Error: ${data.message}</div>`;
                }
            })
            .catch(error => {
                document.getElementById('ticketDetails').innerHTML =
                    `<div class="error">Error: ${error.message}</div>`;
            });
    } else if (action === 'print') {
        fetch(`ticket_ajax.php?action=get_ticket_print&ticket_id=${ticketId}`)
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    const printDiv = document.getElementById('printLayout');
                    printDiv.innerHTML = data.html;
                    window.print();
                } else {
                    alert(`Error: ${data.message}`);
                }
            })
            .catch(error => {
                alert(`Error: ${error.message}`);
            });
    }
}