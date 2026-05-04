<?php
declare(strict_types=1);

// Task content scaffold. Replace values with your real study content later.
return [
    2 => [
        'number' => 2,
        'title' => 'Task 2',
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
    1 => [
        'number' => 1,
        'title' => 'Task 1',
        'ai_correct' => true,
        'work_task' => "You work as a junior employee in the finance and administration department. Your role is to provide guidance based on company policy, but final decisions are made by a manager. A colleague has submitted an expense claim for a recent business trip and wants to know whether the expense can be reimbursed under company policy.\n\n- You are asked to send a short internal reply based on the company's expense reimbursement policy.\n- You may consult the additional documents if needed before making your decision.",
        'scenario' => "Message from colleague:\nHi, I just submitted an expense claim for dinner during my recent business trip.\n\nThe total amount was €68, and I did not request prior approval because the meeting ran late and there were limited dining options nearby.\n\nI kept the receipt and documented the purpose of the meeting in the expense system.\n\nCan this expense still be reimbursed under company policy?",
        'ai_prompt' => "Write a short internal response to this message based on the company policy documents.\nEnsure that your response reflects the relevant rules and requirements.",
        'ai_output' => "Hi,\n\nThank you for submitting the expense claim and documenting the circumstances.\n\nAccording to company policy, meal expenses above the standard limit normally require prior approval. Since prior approval was not obtained, the expense cannot be automatically reimbursed. However, given the circumstances, reimbursement may still be considered following managerial review.\n\nPlease ensure the receipt and a brief explanation are included so the request can be assessed accordingly.\n\nBest regards,",
        'documents' => [
            [
                'key' => 'expense_reimbursement_policy',
                'title' => 'Expense Reimbursement Policy',
                'relevant' => true,
                'content' => "Department: Finance
Policy Title: Expense Reimbursement Policy
Version: 4.0

1. Purpose

This policy defines the rules and procedures for reimbursing employee expenses incurred during business activities.

The objective of this policy is to:

- ensure consistent reimbursement practices
- maintain financial accountability
- control company spending
- support employees in performing their duties

Employees are responsible for understanding and complying with these requirements before submitting expense claims.

2. Scope

This policy applies to:

- all employees
- temporary staff
- contractors authorized to incur business expenses

Covered expenses may include:

- meals during business travel
- transportation
- accommodation
- business-related supplies
3. General Principles

Expenses must be:

- business-related
- reasonable in amount
- properly documented
- compliant with company policy

Employees should make reasonable efforts to manage costs responsibly.

4. Approval Requirements

Certain expenses require prior approval before they are incurred.

Meal expenses exceeding standard limits typically require advance authorization from a manager.

These limits are established to promote responsible spending and ensure budget oversight.

As a general guideline:

Meal expenses above €50 per person normally require prior approval.

5. Exceptional Circumstances

In some situations, employees may incur expenses without prior approval due to unforeseen business needs.

Examples may include:

- meetings running longer than expected
- limited dining options
- urgent operational requirements

In such cases:

Reimbursement may still be considered following managerial review.

However, reimbursement is not guaranteed and will depend on:

- the justification provided
- compliance with documentation requirements
- managerial discretion
6. Documentation Requirements

All expense claims must include:

- a receipt
- date and location
- purpose of the expense
- explanation when required

Incomplete claims may be delayed or rejected.",
            ],
            [
                'key' => 'travel_expense_guidelines',
                'title' => 'Travel and Business Expense Guidelines',
                'relevant' => false,
                'content' => "Department: Operations
Policy Title: Travel and Business Expense Guidelines

1. Purpose

These guidelines provide general recommendations for managing travel-related expenses responsibly.

Employees are encouraged to:

- plan travel in advance
- choose cost-effective options
- maintain accurate expense records
2. Meal Expenses

Meal expenses incurred during business travel are generally reimbursable when:

- the expense is related to business activities
- the amount is reasonable for the location
- proper documentation is provided

Employees should consider local price levels and business circumstances when selecting dining options.

3. Responsibility

Employees are responsible for ensuring that expense claims reflect legitimate business costs.

Managers may review claims to verify compliance with company policies.",
            ],
            [
                'key' => 'workplace_conduct_handbook',
                'title' => 'Workplace Conduct Handbook',
                'relevant' => false,
                'content' => "Department: Human Resources
Policy Title: Workplace Conduct Handbook

1. Purpose

This handbook outlines expectations for professional behavior and responsible decision-making in the workplace.

The organization values:

- professionalism
- integrity
- accountability
- respect
2. Responsible Use of Company Resources

Employees should use company resources responsibly and avoid unnecessary expenses.

Examples include:

- managing time efficiently
- using equipment appropriately
- following company procedures
3. Communication

Employees should communicate clearly and respectfully when interacting with colleagues, clients, and partners.",
            ],
        ],
    ],
];
