<?php
declare(strict_types=1);

// Task content scaffold. Replace values with your real study content later.
return [
    1 => [
        'number' => 1,
        'title' => 'Task 1: Client Meeting Recording',
        'ai_correct' => false,
        'scenario' => "Message from colleague:\nHi, quick question.\nWe have a client meeting next week and I was thinking of recording it so our new team members can use it for internal training later.\nIs that allowed under company policy, as long as the recording stays internal?\nCould you let me know what I should do?",
        'work_task' => "You work as a junior operations coordinator at a mid-sized international company. A colleague from another department has asked whether a client meeting can be recorded and shared internally for training purposes.\n\n- You are asked to send a short internal reply based on the company's policy.\n- You may consult the additional documents if needed before making your decision.",
        'ai_prompt' => "Write a short internal response to this message based on the company policy documents.\nEnsure that your response reflects the relevant rules and requirements.",
        'ai_output' => "Hi,\n\nYes, recording the client meeting for internal training purposes is allowed under company policy, as long as the recording is used only for internal business purposes and stored securely.\n\nYou can go ahead with the recording, but just make sure the file is saved in the approved system and only shared with relevant team members.\n\nLet me know if you need help setting this up.\n\nBest Regards,",
        'documents' => [
            [
                'key' => 'refund_policy',
                'title' => 'Recording Policy',
                'relevant' => true,
                'content' => "Department: Compliance and Risk Management
Policy Title: Recording of Meetings with External Participants
Policy Version: 3.2
Effective Date: January 2024

1. Purpose

This policy establishes the requirements and procedures for recording meetings involving external participants, including clients, suppliers, contractors, and business partners.

The objective of this policy is to:

- protect the privacy and rights of individuals
- ensure compliance with applicable data protection laws and contractual obligations
- maintain transparency in communication practices
- reduce legal and reputational risk to the organization

Employees are responsible for understanding and complying with these requirements before initiating any recording of meetings involving external parties.

2. Scope

This policy applies to:

- all employees
- temporary staff and contractors
- external consultants acting on behalf of the organization

The policy covers:

- video recordings
- audio recordings
- screen recordings
- virtual meeting recordings
- recordings created using collaboration platforms

This policy applies regardless of whether the meeting takes place:

- in person
- remotely
- through video conferencing systems
- through internal communication platforms

3. General Principles

The organization recognizes that recording meetings can provide operational benefits, including:

- documentation of discussions
- support for internal training
- knowledge sharing across teams
- quality assurance and performance improvement

However, recording meetings involving external participants introduces legal and privacy risks if not properly managed. Employees must therefore balance operational needs with privacy and compliance requirements.

4. Requirements for Recording Meetings

Employees may record meetings with external participants only when specific conditions are satisfied.

Before recording any meeting involving clients, partners, or suppliers, employees must ensure that the following requirements are met.

4.1 Participant Consent

Employees must obtain explicit consent from all meeting participants before recording begins.

Consent must be:

- informed
- voluntary
- clearly communicated

Participants must be aware that:

- the meeting is being recorded
- the purpose of the recording
- how the recording will be used
- how long the recording will be retained

If any participant refuses consent, the meeting must not be recorded.

4.2 Manager Approval

In addition to participant consent, employees must obtain prior approval from the relevant department manager before recording meetings involving external participants.

Manager approval ensures that:

- the recording is necessary for legitimate business purposes
- the risks associated with recording are properly assessed
- appropriate safeguards are in place

Approval must be obtained before the meeting takes place.

4.3 Use for Training Purposes

Recordings may be used for internal training only when:

- participant consent has been obtained
- managerial approval has been granted
- the purpose of the recording has been documented

Employees must not assume that internal use automatically makes recording permissible.

5. Storage and Security

All recordings must be stored securely using approved systems.

Employees must ensure that recordings are:

- protected from unauthorized access
- accessible only to authorized personnel
- stored in designated secure locations
- deleted when no longer required

Sharing recordings through personal devices or unapproved platforms is prohibited.

6. Responsibility

Employees initiating recordings are responsible for:

- confirming participant consent
- obtaining required approvals
- ensuring compliance with this policy
- storing recordings securely

Managers are responsible for reviewing and approving recording requests.

7. Non-Compliance

Failure to comply with this policy may result in:

- disciplinary action
- revocation of system access
- legal liability
- reputational damage to the organization",
            ],
            [
                'key' => 'support_sop',
                'title' => 'Data Protection Guidelines',
                'relevant' => false,
                'content' => "Department: Legal and Data Protection
Policy Title: Data Protection and Privacy Guidelines
Policy Version: 5.1

1. Purpose

This guideline outlines the responsibilities of employees when handling personal data within the organization.

The organization is committed to protecting the confidentiality, integrity, and availability of personal data in accordance with applicable data protection laws and internal governance standards.

2. Definition of Personal Data

Personal data refers to any information relating to an identifiable individual.

Examples include:

- names
- contact information
- email addresses
- voice recordings
- video recordings
- identification numbers

Audio and video recordings of individuals typically qualify as personal data.

3. Lawful Processing

Employees must ensure that personal data is processed lawfully, fairly, and transparently.

Processing must be based on:

- legitimate business purposes
- contractual obligations
- legal requirements
- informed consent where required

Employees must avoid collecting or storing personal data unnecessarily.

4. Storage and Retention

Personal data must be stored securely and retained only for as long as necessary to fulfill business or legal requirements.

Employees must:

- use approved storage systems
- restrict access to authorized users
- delete data when no longer required
- avoid storing personal data on personal devices

5. Access Control

Access to personal data must be limited to individuals who require it to perform their job responsibilities.

Employees must not share personal data with unauthorized parties.

6. Reporting Incidents

Employees must report suspected data breaches or unauthorized disclosures immediately to the Data Protection Officer.",
            ],
            [
                'key' => 'tone_guidelines',
                'title' => 'Team Communication Handbook',
                'relevant' => false,
                'content' => "Department: Human Resources
Policy Title: Team Communication and Collaboration Handbook
Policy Version: 2.4

1. Purpose

This handbook provides guidance on effective communication and collaboration practices across teams within the organization.

Strong communication practices support:

- employee engagement
- knowledge sharing
- productivity
- organizational learning
2. Knowledge Sharing

Employees are encouraged to share information and best practices across departments to improve organizational performance.

Examples of knowledge sharing activities include:

- onboarding sessions
- internal training sessions
- team briefings
- documentation of lessons learned

Managers should support knowledge sharing initiatives that enhance employee development.

3. Communication Principles

Employees should communicate clearly and professionally when working with colleagues and stakeholders.

Key communication principles include:

- clarity
- respect
- responsiveness
- accountability

Employees should ensure that information shared internally is accurate and relevant.

4. Collaboration Tools

Employees may use approved communication tools to collaborate with colleagues.

These tools may include:

- email
- messaging platforms
- video conferencing systems
- shared document repositories

Employees should follow organizational guidelines when using communication tools.

5. Professional Conduct

Employees must maintain professional behavior in all communications.

This includes:

- respectful language
- timely responses
- accurate information
- appropriate documentation",
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
