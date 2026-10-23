<?php

declare(strict_types=1);

return [
    'navigation_group' => 'Billing',

    'fields' => [
        'name' => 'Name',
        'email' => 'Email',
        'customer' => 'Customer',
        'status' => 'Status',
        'stripe_id' => 'Stripe ID',
        'created' => 'Created',
    ],

    'customers' => [
        'model_label' => 'customer',
        'plural_model_label' => 'Customers',
        'details' => 'Customer',
        'payment_method' => 'Payment method',
        'no_payment_method' => 'None on file',
        'generic_trial_ends' => 'Generic trial ends',
        'active_subscriptions' => 'Active subscriptions',
        'filter_subscribed' => 'Has an active subscription',
    ],

    'subscriptions' => [
        'model_label' => 'subscription',
        'plural_model_label' => 'Subscriptions',
        'details' => 'Subscription',
        'items' => 'Items',
        'type' => 'Type',
        'price' => 'Price',
        'product' => 'Product',
        'quantity' => 'Quantity',
        'trial_ends' => 'Trial ends',
        'ends' => 'Ends',
        'grace_period' => 'Cancelled; still active until this date.',
        'prices_count' => ':count price|:count prices',
        'filter_ending' => 'Cancelled, still in grace period',
        'badge_tooltip' => 'Past due or unpaid',
    ],

    'invoices' => [
        'title' => 'Invoices',
        'intro' => 'The latest :count invoices from Stripe, read-only. Search and sort work on these; filter by status to load other ones.',
        'number' => 'Number',
        'draft_number' => 'Draft',
        'total' => 'Total',
        'amount_due' => 'Amount due',
        'due' => 'Due',
        'pdf' => 'PDF',
    ],

    'payments' => [
        'title' => 'Payments',
        'intro' => 'The latest :count payments from Stripe, read-only. Refunds and other money movements are done in the Stripe dashboard.',
        'amount' => 'Amount',
        'method' => 'Method',
        'description' => 'Description',
        'receipt' => 'Receipt',
    ],

    'overview' => [
        'mrr' => 'MRR (:currency)',
        'mrr_help' => 'List prices of active and past-due subscriptions',
        'uncounted' => ':count item not counted (metered, tiered or other currency)|:count items not counted (metered, tiered or other currency)',
        'active' => 'Active subscriptions',
        'active_help' => 'None past due',
        'past_due' => ':count past due',
        'trialing' => 'On trial',
        'trialing_help' => 'Not counted in MRR',
        'churn' => 'Churn, last 30 days',
        'churn_help' => ':count cancelled and ended',
    ],

    'actions' => [
        'view' => 'View',
        'open_in_stripe' => 'Open in Stripe',
        'sync' => 'Sync from Stripe',
        'sync_heading' => 'Sync from Stripe?',
        'sync_description_customers' => 'Reads this customer\'s payment method and subscriptions from Stripe and stores them in your Cashier tables. Nothing is changed in Stripe.',
        'sync_description_subscriptions' => 'Reads this subscription from Stripe and stores its status, dates and items in your Cashier tables. Nothing is changed in Stripe.',
    ],

    'notifications' => [
        'synced' => 'Synced from Stripe',
        'synced_subscriptions' => ':count subscription updated|:count subscriptions updated',
        'sync_failed' => 'Sync failed',
        'sync_throttled' => 'Too many syncs. Try again in :seconds seconds.',
    ],

    'status' => [
        'active' => 'Active',
        'trialing' => 'Trialing',
        'past_due' => 'Past due',
        'unpaid' => 'Unpaid',
        'incomplete' => 'Incomplete',
        'incomplete_expired' => 'Incomplete, expired',
        'paused' => 'Paused',
        'canceled' => 'Cancelled',
        'draft' => 'Draft',
        'open' => 'Open',
        'paid' => 'Paid',
        'uncollectible' => 'Uncollectible',
        'void' => 'Void',
        'succeeded' => 'Succeeded',
        'pending' => 'Pending',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
        'partially_refunded' => 'Partially refunded',
    ],

    'empty' => [
        'heading' => 'Nothing here yet',
    ],

    'errors' => [
        'heading' => 'Could not load from Stripe',
        'no_secret' => 'No Stripe secret key is set. Add STRIPE_SECRET to your .env (Cashier reads it as cashier.secret).',
        'authentication' => 'Stripe rejected the API key. Check STRIPE_SECRET.',
        'permission' => 'The API key may not read this. A restricted key needs read access to customers, subscriptions, invoices, charges and prices.',
        'rate_limit' => 'Stripe\'s rate limit was reached. Try again in a moment.',
        'connection' => 'Could not reach Stripe. Check the server\'s network and try again.',
        'api' => 'Stripe returned an error:',
        'customer_mismatch' => 'Stripe says this subscription belongs to customer :customer, which is not this subscription\'s owner here. Nothing was changed.',
        'unexpected' => 'Something went wrong while reading from Stripe. The error was logged.',
    ],
];
