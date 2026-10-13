<?php
// the PDF receipt of an order: attached to the order emails and downloadable from order/receipt.php
// the order and its items come from the order_summary_view and order_details_view views

function pdf_money($amount)
{
    // the built-in PDF fonts have no peso sign, so write "PHP"
    return 'PHP ' . number_format((float)$amount, 2);
}

// returns the PDF as a string, or null when the order does not exist
function order_receipt_pdf($conn, $order_id)
{
    $order = mysqli_fetch_assoc(mysqli_execute_query($conn,
        "SELECT * FROM order_summary_view WHERE order_id = ? LIMIT 1", [$order_id]));
    if (!$order) {
        return null;
    }
    $items = mysqli_fetch_all(mysqli_execute_query($conn,
        "SELECT product_name, brand, size, unit, quantity, price, subtotal
         FROM order_details_view WHERE order_id = ? ORDER BY order_item_id", [$order_id]), MYSQLI_ASSOC);

    $id = (int)$order['order_id'];
    $maroon = array(139, 30, 63);
    $grey = array(110, 110, 110);
    $left = 50;
    $right = 545;

    $pdf = new SimplePdf();
    $pdf->setTitle("Receipt for order #{$id}");
    $pdf->addPage();

    // store name band
    $pdf->fillRect(0, 0, SimplePdf::WIDTH, 72, $maroon);
    $pdf->text($left, 44, MAIL_FROM_NAME, 18, true, 'left', array(255, 255, 255));
    $pdf->text($right, 44, 'TRANSACTION RECEIPT', 11, true, 'right', array(255, 255, 255));

    // order details
    $y = 110;
    $pdf->text($left, $y, "Order #{$id}", 16, true);
    $pdf->text($right, $y, 'Status: ' . status_label($order['status']), 11, true, 'right', $maroon);
    $y += 22;
    $details = array(
        'Order date' => date('F j, Y, g:i a', strtotime($order['created_at'])),
        'Payment' => payment_label($order['payment_method']) . ' (' . ($order['payment_status'] === 'paid' ? 'Paid' : 'Unpaid') . ')',
    );
    if ($order['payment_status'] === 'paid' && $order['paid_at']) {
        $details['Paid on'] = date('F j, Y, g:i a', strtotime($order['paid_at']));
    }
    $details['Receipt issued'] = date('F j, Y, g:i a');
    foreach ($details as $label => $value) {
        $pdf->text($left, $y, $label . ':', 10, true);
        $pdf->text($left + 95, $y, $value, 10);
        $y += 15;
    }

    // customer and delivery address side by side
    $y += 12;
    $pdf->text($left, $y, 'BILLED TO', 9, true, 'left', $grey);
    $pdf->text(310, $y, 'SHIP TO', 9, true, 'left', $grey);
    $y += 15;
    $pdf->text($left, $y, SimplePdf::fit($order['customer_name'], 240, 10), 10, true);
    $pdf->text($left, $y + 14, SimplePdf::fit($order['customer_email'], 240, 10), 10);
    $addressY = $y;
    foreach (SimplePdf::wrap($order['shipping_address'], 235, 10) as $line) {
        $pdf->text(310, $addressY, $line, 10);
        $addressY += 14;
    }
    $y = max($y + 28, $addressY) + 18;

    // the items table; the header row is drawn again on every new page
    $tableHeader = function ($y) use ($pdf, $left, $right) {
        $pdf->fillRect($left, $y, $right - $left, 22, array(240, 240, 240));
        $pdf->text($left + 8, $y + 15, 'Product', 10, true);
        $pdf->text(375, $y + 15, 'Unit price', 10, true, 'right');
        $pdf->text(430, $y + 15, 'Qty', 10, true, 'right');
        $pdf->text($right - 8, $y + 15, 'Subtotal', 10, true, 'right');
        return $y + 22;
    };
    $y = $tableHeader($y);

    foreach ($items as $item) {
        if ($y > 740) {
            $pdf->text($right, 815, 'continued on the next page', 8, false, 'right', $grey);
            $pdf->addPage();
            $y = $tableHeader(50);
        }
        $pdf->text($left + 8, $y + 15, SimplePdf::fit($item['product_name'], 240, 10), 10);
        $pdf->text($left + 8, $y + 28, SimplePdf::fit($item['brand'] . ' - ' . $item['size'] . ' ' . $item['unit'], 240, 8), 8, false, 'left', $grey);
        $pdf->text(375, $y + 15, pdf_money($item['price']), 10, false, 'right');
        $pdf->text(430, $y + 15, (string)(int)$item['quantity'], 10, false, 'right');
        $pdf->text($right - 8, $y + 15, pdf_money($item['subtotal']), 10, false, 'right');
        $y += 36;
        $pdf->line($left, $y, $right, $y);
    }

    // totals
    if ($y > 720) {
        $pdf->addPage();
        $y = 50;
    }
    $y += 22;
    $units = (int)$order['total_units'];
    $pdf->text($left + 8, $y, $units . ' item' . ($units === 1 ? '' : 's') . ' in this order', 10, false, 'left', $grey);
    $pdf->text(430, $y, 'Grand Total', 12, true, 'right');
    $pdf->text($right - 8, $y, pdf_money($order['grand_total']), 12, true, 'right', $maroon);
    $y += 18;
    if ($order['status'] === 'cancelled') {
        $note = 'This order was cancelled. Nothing is due.';
    } else if ($order['payment_status'] === 'paid') {
        $note = 'Paid in full. Thank you!';
    } else {
        $note = 'Amount due on delivery: ' . pdf_money($order['grand_total']);
    }
    $pdf->text($right - 8, $y, $note, 9, false, 'right', $grey);

    // footer
    $pdf->line($left, 790, $right, 790);
    $pdf->text($left, 805, 'Thank you for shopping with ' . MAIL_FROM_NAME . '.', 9, false, 'left', $grey);
    $pdf->text($right, 805, APP_URL, 9, false, 'right', $grey);

    return $pdf->output();
}

// the receipt in the shape send_mail() expects for an attachment
function order_receipt_attachment($conn, $order_id)
{
    $data = order_receipt_pdf($conn, $order_id);
    if ($data === null) {
        return array();
    }
    return array(array('name' => 'receipt-order-' . (int)$order_id . '.pdf', 'type' => 'application/pdf', 'data' => $data));
}
