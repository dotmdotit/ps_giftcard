<?php
/**
 * Module texts (English). Same keys as lang/it.php; missing keys fall back to Italian.
 *
 * @author dotm.it
 * @license MIT (see LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

return [
    // purchase page
    'buy_title' => 'Gift cards',
    'buy_meta_title' => 'Give a gift card',
    'buy_meta_description' => 'Choose the design, the amount and how to deliver it: by email on a date of your choice, printed at home or by post.',
    'buy_kicker' => 'Give a gift card',
    'buy_intro' => 'Choose a design and an amount, write a message and decide how to deliver it: by email on the day you choose, printed at home or by post.',
    'buy_unavailable' => 'Gift cards are not available at the moment.',
    'buy_note' => 'The code is created once the payment is confirmed.',
    'buy_link' => 'Give a gift card',
    'step_template' => 'Choose a design',
    'step_delivery' => 'How should we deliver it',
    'step_amount' => 'Choose the amount',
    'step_message' => 'Your message',
    'filter_occasion' => 'Filter by occasion',
    'filter_all' => 'All',
    'amounts_digital' => 'Digital gift card',
    'amounts_physical' => 'Physical card (sent by post)',
    'field_recipient' => 'To (recipient’s name)',
    'field_sender' => 'From (your name)',
    'field_email' => 'Recipient’s email',
    'field_email_confirm' => 'Repeat the recipient’s email',
    'field_date' => 'Delivery date',
    'field_date_help' => 'The gift card arrives in the morning of the chosen day.',
    'field_message' => 'Message',
    'optional' => 'optional',
    'field_quantity' => 'Quantity',
    'btn_add' => 'Add to cart',
    'preview' => 'Preview',
    'preview_for' => 'To',
    'preview_from' => 'From',
    'card_label' => 'Gift card',

    // delivery
    'delivery_email' => 'By email to the recipient',
    'delivery_email_help' => 'Delivered to the recipient on the day you choose, with your message and a printable PDF.',
    'delivery_print' => 'Print at home',
    'delivery_print_help' => 'You receive the gift card as a PDF right after payment, to print or forward.',
    'delivery_post' => 'By post',
    'delivery_post_help' => 'We ship a physical gift card to the order’s delivery address.',

    // errors
    'error_token' => 'Your session has expired: reload the page and try again.',
    'error_delivery' => 'Choose how to deliver the gift card.',
    'error_amount' => 'Choose an amount.',
    'error_template' => 'Choose a design.',
    'error_sender' => 'Enter your name.',
    'error_recipient' => 'Enter the recipient’s name.',
    'error_name_chars' => 'Names cannot contain the characters < > = { } # ;',
    'error_email' => 'Enter a valid email for the recipient.',
    'error_email_confirm' => 'The two recipient emails do not match.',
    'error_date' => 'Choose a delivery date between today and one year from now.',
    'error_message' => 'The message can be at most {n} characters long.',
    'error_message_chars' => 'The message contains characters that are not allowed.',
    'error_quantity' => 'You can add from 1 to {n} gift cards at a time.',
    'error_cart' => 'The gift card could not be added to the cart. Please try again.',
    'vouchers_removed' => 'Discount codes cannot be used on orders containing gift cards: we removed them from your cart.',

    // cart, order and invoice summary
    'field_label' => 'Gift card',
    'sum_for' => 'To: {name}',
    'sum_from' => 'From: {name}',
    'sum_email_now' => 'By email to {email} after payment',
    'sum_email_on' => 'By email to {email} on {date}',
    'sum_print' => 'Printable PDF sent to the buyer',
    'sum_post' => 'Physical card by post',
    'sum_template' => 'Design: {name}',
    'sum_message' => 'Message: “{text}”',

    // balance check
    'balance_title' => 'Gift card balance',
    'balance_intro' => 'Enter your gift card code to see the available balance and the expiry date.',
    'balance_link' => 'Check a gift card balance',
    'balance_not_found' => 'Code not found. Please check that you typed it correctly.',
    'balance_rate_limited' => 'Too many checks in a short time. Please try again in an hour.',
    'btn_check' => 'Check balance',
    'code_label' => 'Code',
    'col_balance' => 'Available balance',
    'col_amount' => 'Initial value',
    'col_state' => 'Status',
    'col_expire' => 'Expires',
    'no_expiry_short' => 'Never',

    // customer account
    'account_link' => 'My gift cards',
    'account_title' => 'My gift cards',
    'account_empty' => 'You have no gift cards yet.',
    'account_bought' => 'Gift cards you bought',
    'account_received' => 'Gift cards you received',
    'sent_on' => 'sent on',
    'scheduled_on' => 'scheduled for',
    'btn_pdf' => 'Download PDF',

    // states
    'state_pending' => 'Awaiting payment',
    'state_active' => 'Active',
    'state_used' => 'Used',
    'state_expired' => 'Expired',
    'state_cancelled' => 'Cancelled',

    // usage
    'valid_until' => 'Valid until {date}.',
    'no_expiry' => 'No expiry date.',
    'usage_partial' => 'It can be used across several orders: any unspent balance stays on the same code.',
    'usage_single' => 'It can be used in a single order: any unspent amount is not kept.',

    // emails
    'mail_subject_recipient' => 'You received a gift card',
    'mail_subject_recipient_from' => '{sender} sent you a gift card',
    'mail_subject_buyer' => 'Your gift card is ready',
    'mail_subject_sent' => 'The gift card for {recipient} has been delivered',

    // PDF
    'pdf_title' => 'Gift card',
    'pdf_for' => 'To {name}',
    'pdf_from' => 'From {name}',
    'pdf_amount' => 'Value: {amount}.',
    'pdf_how' => 'How to use it: choose your products on {url} and enter the code in the cart, in the discount code field.',
    'pdf_balance' => 'Balance and expiry: {url}',

    // catalog and vouchers
    'category_name' => 'Gift cards',
    'product_name' => 'Gift card {amount}',
    'cart_rule_name' => 'Gift card',
    'payment_label' => 'Gift card',
    'tpl_classic' => 'Classic',
    'tpl_birthday' => 'Birthday',
    'tpl_christmas' => 'Christmas',
    'tpl_love' => 'With love',
    'tpl_minimal' => 'Minimal',
    'occ_any' => 'Any occasion',
    'occ_birthday' => 'Birthday',
    'occ_christmas' => 'Christmas',
    'occ_love' => 'Love',
];
