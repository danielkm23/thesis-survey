<?php
declare(strict_types=1);

// Task content scaffold. Replace values with your real study content later.
return [
    1 => [
        'number' => 1,
        'title' => 'Task 1: Client Meeting Recording',
        'ai_correct' => false,
        'scenario' => "You work as a junior operations coordinator at a mid-sized international company. A colleague from another department has asked whether a client meeting can be recorded and shared internally for training purposes.\n\nYou are asked to send a short internal reply based on the company's policy.\n\nYou may consult the additional documents if needed before making your decision.",
        'work_task' => "Message from colleague:\n\nHi, quick question. We have a client meeting next week and I was thinking of recording it so our new team members can use it for internal training later.\n\nIs that allowed under company policy, as long as the recording stays internal? Could you let me know what I should do?",
        'ai_output' => "Yes, this should generally be allowed as long as the recording is only used internally for training purposes and is not shared outside the company.\n\nInternal business use is typically permitted under company policy.\nYou should make sure the recording is stored securely and only accessible to relevant staff, but no further approval is usually required.",
        'documents' => [
            [
                'key' => 'refund_policy',
                'title' => 'Recording Policy',
                'relevant' => true,
                'content' => 'Duplicate charge refunds are available after transaction verification. Standard processing time is 3-5 business days. Agents must not promise same-day settlement unless payment operations has confirmed it.',
            ],
            [
                'key' => 'support_sop',
                'title' => 'Data Protection Guidelines',
                'relevant' => false,
                'content' => 'For billing disputes, support must collect order ID, billing date, and last 4 digits of payment method. If duplicate charge is likely, create a Billing Ops ticket and share expected timeline with the customer.',
            ],
            [
                'key' => 'tone_guidelines',
                'title' => 'Team Communication Handbook',
                'relevant' => false,
                'content' => 'Use clear and empathetic language. Acknowledge frustration, avoid absolute guarantees, and provide concrete next steps. Keep messages concise and professional.',
            ],
        ],
    ],
    2 => [
        'number' => 2,
        'title' => 'Task 2: Clarify a Shipping Delay',
        'ai_correct' => true,
        'scenario' => 'A customer asks why their package is delayed and requests a clear estimate for delivery.',
        'ai_output' => 'Thanks for reaching out. Your package is currently delayed at a regional hub due to weather conditions. The latest estimated delivery is within 2 business days. We will notify you if this changes.',
        'documents' => [
            [
                'key' => 'shipping_policy',
                'title' => 'Shipping Timeline Policy',
                'relevant' => true,
                'content' => 'Weather and carrier disruptions may extend delivery windows by up to 2 business days. Customers should receive transparent timeline updates.',
            ],
            [
                'key' => 'tracking_faq',
                'title' => 'Tracking FAQ',
                'relevant' => false,
                'content' => 'Tracking statuses can remain unchanged during hub scans. This does not always indicate package loss.',
            ],
            [
                'key' => 'brand_voice',
                'title' => 'Brand Voice Guide',
                'relevant' => false,
                'content' => 'Use calm and clear language. Avoid technical jargon unless necessary for explanation.',
            ],
        ],
    ],
    3 => [
        'number' => 3,
        'title' => 'Task 3: Respond to Data Deletion Request',
        'ai_correct' => false,
        'scenario' => 'A user asks to permanently delete their account data and wants confirmation that deletion is immediate and irreversible.',
        'ai_output' => 'We have deleted all your data immediately and permanently. It cannot be recovered under any circumstances.',
        'documents' => [
            [
                'key' => 'privacy_policy',
                'title' => 'Privacy and Data Retention Policy',
                'relevant' => true,
                'content' => 'Account deletion requests are processed within 30 days. Some records may be retained temporarily for legal and fraud-prevention obligations.',
            ],
            [
                'key' => 'deletion_process',
                'title' => 'Account Deletion Process',
                'relevant' => false,
                'content' => 'Support must confirm user identity and communicate expected completion timelines clearly.',
            ],
            [
                'key' => 'communication_style',
                'title' => 'Support Communication Style',
                'relevant' => false,
                'content' => 'Avoid absolute language if policies include exceptions. Be transparent about timelines and constraints.',
            ],
        ],
    ],
    4 => [
        'number' => 4,
        'title' => 'Task 4: Explain Subscription Cancellation Terms',
        'ai_correct' => true,
        'scenario' => 'A customer wants to cancel their annual subscription and asks whether they will lose access immediately.',
        'ai_output' => 'You can cancel your annual subscription at any time. Your plan will remain active until the end of the current billing period, and you will not be charged again after that.',
        'documents' => [
            [
                'key' => 'subscription_terms',
                'title' => 'Subscription Terms',
                'relevant' => true,
                'content' => 'Annual subscriptions remain active until the end of the paid term. Cancellation stops renewal but does not end current access immediately.',
            ],
            [
                'key' => 'billing_rules',
                'title' => 'Billing and Renewal Rules',
                'relevant' => false,
                'content' => 'No prorated refunds are issued for mid-cycle annual cancellations unless local law requires otherwise.',
            ],
            [
                'key' => 'reply_templates',
                'title' => 'Response Templates',
                'relevant' => false,
                'content' => 'Use concise responses with one clear policy statement and one next-step sentence.',
            ],
        ],
    ],
];
