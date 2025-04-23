<?php
// Generate HTML for ticket details view or print
function generateTicketDetailsHTML($ticketDetail) {
    ob_start();
    ?>
    <div class="form-row">
        <div class="form-group">
            <label>Ticket ID:</label>
            <p><?php echo $ticketDetail['TicketID']; ?></p>
        </div>
        <div class="form-group">
            <label>Status:</label>
            <p><span class="status-<?php echo $ticketDetail['Status']; ?>"><?php echo ucfirst($ticketDetail['Status']); ?></span></p>
        </div>
        <div class="form-group">
            <label>Priority:</label>
            <p><span class="priority-<?php echo $ticketDetail['Priority']; ?>"><?php echo ucfirst($ticketDetail['Priority']); ?></span></p>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Created By:</label>
            <p><?php echo htmlspecialchars($ticketDetail['CreatedByName']); ?></p>
        </div>
        <div class="form-group">
            <label>Assigned To:</label>
            <p><?php echo $ticketDetail['AssignedToName'] ? htmlspecialchars($ticketDetail['AssignedToName']) : 'Not assigned'; ?></p>
        </div>
        <div class="form-group">
            <label>Category:</label>
            <p><?php echo $ticketDetail['CategoryName'] ? htmlspecialchars($ticketDetail['CategoryName']) : 'Uncategorized'; ?></p>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label>Created Date:</label>
            <p><?php echo date('M d, Y H:i', strtotime($ticketDetail['CreatedDate'])); ?></p>
        </div>
        <div class="form-group">
            <label>Resolved Date:</label>
            <p><?php echo $ticketDetail['ResolvedDate'] ? date('M d, Y H:i', strtotime($ticketDetail['ResolvedDate'])) : 'Not resolved yet'; ?></p>
        </div>
        <div class="form-group">
            <label>Time Spent:</label>
            <p><?php echo $ticketDetail['TimeSpent'] ? $ticketDetail['TimeSpent'] . ' minutes' : 'Not recorded'; ?></p>
        </div>
    </div>

    <div class="form-group">
        <label>Description:</label>
        <div style="padding: 10px; background-color: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
            <?php echo nl2br(htmlspecialchars($ticketDetail['Description'])); ?>
        </div>
    </div>

    <?php if ($ticketDetail['ResolutionNotes']): ?>
        <div class="form-group">
            <label>Resolution Notes:</label>
            <div style="padding: 10px; background-color: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                <?php echo nl2br(htmlspecialchars($ticketDetail['ResolutionNotes'])); ?>
            </div>
        </div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

// Generate HTML for ticket print layout
function generateTicketPrintHTML($ticketDetail) {
    ob_start();
    ?>
    <div class="print-layout">
        <div class="print-header">
            <h1>HELP DESK TICKET</h1>
            <h2>Ticket #<?php echo $ticketDetail['TicketID']; ?></h2>
        </div>

        <div class="print-section">
            <div class="print-row">
                <div class="print-label">Status:</div>
                <div class="print-value"><?php echo ucfirst($ticketDetail['Status']); ?></div>
            </div>
            <div class="print-row">
                <div class="print-label">Priority:</div>
                <div class="print-value"><?php echo ucfirst($ticketDetail['Priority']); ?></div>
            </div>
            <div class="print-row">
                <div class="print-label">Category:</div>
                <div class="print-value"><?php echo $ticketDetail['CategoryName'] ? htmlspecialchars($ticketDetail['CategoryName']) : 'Uncategorized'; ?></div>
            </div>
        </div>

        <div class="print-section">
            <div class="print-row">
                <div class="print-label">Created By:</div>
                <div class="print-value"><?php echo htmlspecialchars($ticketDetail['CreatedByName']); ?></div>
            </div>
            <div class="print-row">
                <div class="print-label">Created Date:</div>
                <div class="print-value"><?php echo date('M d, Y H:i', strtotime($ticketDetail['CreatedDate'])); ?></div>
            </div>
            <div class="print-row">
                <div class="print-label">Assigned To:</div>
                <div class="print-value"><?php echo $ticketDetail['AssignedToName'] ? htmlspecialchars($ticketDetail['AssignedToName']) : 'Not assigned'; ?></div>
            </div>
        </div>

        <div class="print-section">
            <h3>Issue Description:</h3>
            <div class="print-description">
                <?php echo nl2br(htmlspecialchars($ticketDetail['Description'])); ?>
            </div>
        </div>

        <?php if ($ticketDetail['ResolutionNotes']): ?>
            <div class="print-section">
                <h3>Resolution Notes:</h3>
                <div class="print-description">
                    <?php echo nl2br(htmlspecialchars($ticketDetail['ResolutionNotes'])); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($ticketDetail['ResolvedDate']): ?>
            <div class="print-section">
                <div class="print-row">
                    <div class="print-label">Resolved Date:</div>
                    <div class="print-value"><?php echo date('M d, Y H:i', strtotime($ticketDetail['ResolvedDate'])); ?></div>
                </div>
                <div class="print-row">
                    <div class="print-label">Time Spent:</div>
                    <div class="print-value"><?php echo $ticketDetail['TimeSpent'] ? $ticketDetail['TimeSpent'] . ' minutes' : 'Not recorded'; ?></div>
                </div>
            </div>
        <?php endif; ?>

        <div class="print-section">
            <div class="print-row">
                <div class="print-label">Technician Signature:</div>
                <div class="print-value">____________________________</div>
            </div>
        </div>

        <div class="print-footer">
            <p>Generated on <?php echo date('M d, Y H:i:s'); ?></p>
            <p>Front Desk Management System - Help Desk</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>