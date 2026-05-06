<?php
declare(strict_types=1);

// Task content scaffold. Replace values with your real study content later.
return [
    2 => [
        'number' => 2,
        'title' => 'Task 2',
        // Design intent: Task 2 uses an intentionally AI-incorrect draft.
        'ai_correct' => false,
        'decision_summary' => 'Colleague request: record an upcoming client meeting for internal training; asks whether internal-only recording is allowed under policy; asks what to do before the meeting.',
        'scenario' => "Quick policy check: we have a client meeting next week, and I want to record it for internal training.\n\nIf the recording stays internal, is this allowed under company policy?\n\nWhat should I do before the meeting?",
        'work_task' => "You work as a junior operations coordinator at a mid-sized international company. Part of your role is to help colleagues follow internal policies for meetings, data handling, and client communication. You do not give legal approval yourself, but you are expected to point colleagues to the correct policy requirements before they take action.\n\nA colleague has sent you the message below. An internal AI assistant has also generated a draft response. Your task is to decide what you would actually send back to the colleague.\n\nYou may consult the available company documents before making your decision.",
        'ai_prompt' => "Write a short internal response to this message based on the company policy documents.\nEnsure that your response reflects the relevant rules and requirements.",
        'ai_output' => "This recording is likely allowed because it has an internal training purpose and would be stored in an approved company system. Access should be limited to relevant employees.",
        'response_options' => [
            [
                'key' => 'ai_consistent_wrong',
                'text' => "This is likely allowed because it is for internal training and can be stored securely.",
            ],
            [
                'key' => 'correct',
                'text' => "This can only proceed if all participants give explicit consent and a manager approves it in advance.",
            ],
            [
                'key' => 'too_strict',
                'text' => "This cannot be recorded and should be escalated to legal immediately.",
            ],
            [
                'key' => 'other',
                'text' => "Other — I would write something else.",
            ],
        ],
        'documents' => [
            [
                'key' => 'meeting_recording_consent_policy',
                'title' => 'Meeting Recording and Consent Policy',
                'relevant' => true,
                'content' => "Key rules:
- Meetings may only be recorded when there is a clear business purpose.
- Client meetings require explicit consent from all participants.
- Manager approval is required before recording.
- Recordings must be stored in approved company systems.
- If consent or approval is missing, the meeting must not be recorded.",
            ],
            [
                'key' => 'internal_training_materials_policy',
                'title' => 'Internal Training Materials Policy',
                'relevant' => false,
                'content' => "Key points:
- Employees may create internal training materials for learning and onboarding.
- Training materials should be accurate and relevant.
- Real business examples should avoid unnecessary personal or confidential details.
- Materials should be stored in approved company systems.",
            ],
            [
                'key' => 'information_security_data_storage_policy',
                'title' => 'Information Security and Data Storage Policy',
                'relevant' => false,
                'content' => "Key points:
- Company files must be stored only in approved systems.
- Access should be limited to employees who need the information.
- Files with client information should be handled carefully.
- Work-related files should not be saved on personal devices or private cloud storage.",
            ],
        ],
    ],
    1 => [
        'number' => 1,
        'title' => 'Task 1',
        // Design intent: Task 1 uses an AI-correct draft.
        'ai_correct' => true,
        'decision_summary' => 'Colleague request: €68 dinner expense during business trip; no prior approval; late meeting/limited dining options; receipt and meeting purpose documented.',
        'work_task' => "You work in the finance and administration department of a mid-sized international company. Part of your role is to give colleagues guidance on expense claims based on company policy. You do not make the final reimbursement decision yourself; claims that require an exception or approval are reviewed by a manager.\n\nA colleague has sent you the message below. An internal AI assistant has also generated a draft response. Your task is to decide what you would actually send back to the colleague.\n\nYou may consult the available company documents before making your decision.",
        'scenario' => "I submitted a dinner expense from a recent business trip.\n\nThe amount was EUR 68, and I did not get prior approval because the meeting ran late and nearby options were limited.\n\nI kept the receipt and documented the meeting purpose in the expense system. Can this still be reimbursed under policy?",
        'ai_prompt' => "Write a short internal response to this message based on the company policy documents.\nEnsure that your response reflects the relevant rules and requirements.",
        'ai_output' => "The claim cannot be approved automatically because it appears to exceed the normal dinner allowance and prior approval was not obtained. However, a manager may still review it as an exception if the receipt and explanation are complete.",
        'response_options' => [
            [
                'key' => 'ai_consistent_wrong',
                'text' => "This can likely be reimbursed because it was a business trip and documentation was provided.",
            ],
            [
                'key' => 'correct',
                'text' => "This cannot be approved automatically, but a manager may review it as an exception if the receipt and explanation are complete.",
            ],
            [
                'key' => 'too_strict',
                'text' => "This cannot be reimbursed because prior approval was not obtained.",
            ],
            [
                'key' => 'other',
                'text' => "Other — I would write something else.",
            ],
        ],
        'documents' => [
            [
                'key' => 'expense_reimbursement_policy',
                'title' => 'Expense Reimbursement Policy',
                'relevant' => true,
                'content' => "Key rules:
- Business meals during work travel may be reimbursed when documented.
- Dinner limit: €60 per person.
- Above-limit meals require prior approval.
- Without prior approval, the claim is not automatically approved.
- A manager may review exceptions if a receipt and explanation are provided.",
            ],
            [
                'key' => 'travel_business_expense_guidelines',
                'title' => 'Travel and Business Expense Guidelines',
                'relevant' => false,
                'content' => "Key points:
- Employees should make reasonable choices when booking travel, accommodation, and meals.
- Receipts should be kept for travel-related expenses.
- The business purpose should be recorded in the expense system.
- Expenses should normally be submitted within 30 days.",
            ],
            [
                'key' => 'workplace_conduct_handbook',
                'title' => 'Workplace Conduct Handbook',
                'relevant' => false,
                'content' => "Key points:
- Employees should use company resources responsibly.
- Requests and expenses should be submitted honestly and clearly.
- Managers may ask for more information when a request is unclear or unusual.
- Employees are expected to cooperate with internal procedures.",
            ],
        ],
    ],
];
