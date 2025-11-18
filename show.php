<?php
require 'server/db_connection.php'; // your PDO connection

$pnr = $_GET['pnr'] ?? null;
$applicationData = null;

if ($pnr) {
    // 1. Fetch application info
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE pnr = ?");
    $stmt->execute([$pnr]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($application) {
        // 2. Fetch all applicants
        $stmt2 = $pdo->prepare("SELECT * FROM applicants WHERE pnr = ?");
        $stmt2->execute([$pnr]);
        $appRows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $applicants = [];
        foreach ($appRows as $ap) {
            $applicants[] = [
                "pnr" => $ap['pnr'],
                "user_pnr" => $ap['user_pnr'],
                "completed" => (bool)$ap['completed'],
                "passportInfo" => json_decode($ap['passport_info'], true),
                "nidInfo" => json_decode($ap['nid_info'], true),
                "contactInfo" => json_decode($ap['contact_info'], true),
                "familyInfo" => json_decode($ap['family_info'], true),
                "accommodationDetails" => json_decode($ap['accommodation_details'], true),
                "employmentInfo" => json_decode($ap['employment_info'], true),
                "incomeExpenditure" => json_decode($ap['income_expenditure'], true),
                "travelInfo" => json_decode($ap['travel_info'], true),
                "travelHistory" => json_decode($ap['travel_history'], true)
            ];
        }

        // 3. Combine into JS-friendly structure
        $applicationData = [
            'pnr' => $application['pnr'],
            'totalApplicants' => count($applicants),
            'timestamp' => $application['created_at'],
            'applicants' => $applicants
        ];
    }
}

// Pass $applicationData to JS
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UK Visa Application Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .copy-field {
            cursor: pointer;
            transition: all 0.2s ease;
            border-radius: 0.375rem;
            padding: 0.25rem 0.5rem;
            margin: 0.125rem 0;
            border: 1px solid #e5e7eb;
            background-color: white;
            font-family: inherit;
        }

        .copy-field:hover {
            background-color: #f3f4f6;
            border-color: #3b82f6;
        }

        .copy-field.copied {
            background-color: #d1fae5;
            color: #065f46;
            border-color: #10b981;
        }

        .applicant-section {
            border-left: 4px solid #3b82f6;
            background-color: #f8fafc;
            transition: all 0.3s ease;
        }

        .applicant-section.highlighted {
            border-left-color: #10b981;
            background-color: #f0fdf4;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }

        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            background-color: #10b981;
            color: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }

        .toast.show {
            transform: translateX(0);
        }

        .step-progress {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding: 1rem;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .step-item {
            text-align: center;
            flex: 1;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .step-item:hover {
            transform: translateY(-2px);
        }

        .step-item:not(:last-child):after {
            content: '';
            position: absolute;
            top: 20px;
            right: -50%;
            width: 100%;
            height: 2px;
            background-color: #e5e7eb;
            z-index: 1;
        }

        .step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            position: relative;
            z-index: 2;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .step-icon.active {
            background-color: #3b82f6;
            color: white;
        }

        .step-icon.highlighted {
            background-color: #10b981;
            color: white;
            transform: scale(1.1);
        }

        .step-label {
            font-size: 0.75rem;
            color: #6b7280;
            transition: all 0.3s ease;
        }

        .step-label.active {
            color: #3b82f6;
            font-weight: 600;
        }

        .step-label.highlighted {
            color: #10b981;
            font-weight: 700;
        }

        .applicant-tab {
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }

        .applicant-tab.active {
            background-color: #3b82f6;
            color: white;
            border-bottom-color: #1d4ed8;
        }

        .applicant-tab:hover:not(.active) {
            background-color: #f3f4f6;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <!-- Header -->
        <header class="text-center mb-8">
            <div class="flex items-center justify-center mb-4">
                <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-file-alt text-white text-xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-gray-800">UK Visa Application Details</h1>
            </div>
            <p class="text-gray-600 max-w-2xl mx-auto">View and copy application information. Click on any value to copy it to clipboard.</p>
        </header>

        <!-- Application Details -->
        <div id="application-container" class="space-y-6">
            <!-- Application will be loaded here -->
        </div>

        <!-- Action Buttons -->
        <div class="mt-8 flex justify-center space-x-4">
            <button id="back-to-dashboard" class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-6 rounded-lg transition duration-300 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
            </button>
            <button id="download-json" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition duration-300 flex items-center">
                <i class="fas fa-download mr-2"></i> Download JSON
            </button>
        </div>

        <!-- Toast Notification -->
        <div id="toast" class="toast hidden">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span id="toast-message">Copied to clipboard!</span>
            </div>
        </div>
    </div>

    <script>
        let applicationData = <?php
                                // If DB record exists, send JSON to JS
                                if ($applicationData) {
                                    echo json_encode($applicationData);
                                } else {
                                    echo 'null';
                                }
                                ?>;
        let currentApplicantIndex = 0; // Changed from const to let
        const stepSections = [
            'passport-info', 'nid-info', 'contact-info', 'family-info',
            'accommodation-info', 'employment-info', 'income-info',
            'travel-info', 'travel-history'
        ];

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            if (!applicationData) {
                const savedApplication = localStorage.getItem('ukVisaApplication-<?php echo $pnr; ?>');
                if (savedApplication) {
                    applicationData = JSON.parse(savedApplication);
                }
            }

            if (!applicationData) {
                // Show error if nothing found
                document.getElementById('application-container').innerHTML = `
                <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-red-800 mb-2">Application Not Found</h3>
                    <p class="text-red-600">No application found in DB or localStorage for PNR: <?php echo $pnr ?? 'N/A'; ?></p>
                    <button onclick="window.location.href='index.php'" class="mt-4 bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg transition duration-300">
                        Back to Dashboard
                    </button>
                </div>
            `;
                return;
            }

            // Otherwise, render the application as before
            renderApplication();
            setupEventListeners();
        });

        // Set up event listeners
        function setupEventListeners() {
            document.getElementById('back-to-dashboard').addEventListener('click', function() {
                window.location.href = 'index.php';
            });

            document.getElementById('download-json').addEventListener('click', downloadJSON);
        }

        // Render the application
        function renderApplication() {
            const container = document.getElementById('application-container');

            let html = `
            <!-- Application Header -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="section-header px-6 py-4 text-white">
                    <div class="flex flex-col md:flex-row md:items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-bold">Application Overview</h2>
                            <p class="text-blue-100">PNR: ${applicationData.pnr}</p>
                        </div>
                        <div class="mt-2 md:mt-0 text-sm">
                            <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full">
                                ${applicationData.totalApplicants} Applicant(s)
                            </span>
                            <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full ml-2">
                                ${new Date(applicationData.timestamp).toLocaleDateString()}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        `;

            // Applicant Tabs for multiple applicants
            if (applicationData.totalApplicants > 1) {
                html += `
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="border-b border-gray-200">
                        <div class="flex overflow-x-auto" id="applicant-tabs">
                            ${applicationData.applicants.map((applicant, index) => `
                                <div class="applicant-tab py-3 px-6 text-sm font-medium flex items-center min-w-40 ${
                                    index === currentApplicantIndex ? 'active bg-blue-600 text-white' : 'text-gray-500 bg-white'
                                }" data-applicant="${index}">
                                    <i class="fas fa-user mr-2 ${index === currentApplicantIndex ? 'text-white' : 'text-gray-400'}"></i>
                                    Applicant ${index + 1}
                                    ${applicant.completed ? 
                                        `<i class="fas fa-check-circle ml-2 ${index === currentApplicantIndex ? 'text-white' : 'text-green-500'}"></i>` : 
                                        ''
                                    }
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            `;
            }

            // Render current applicant
            html += renderApplicant(applicationData.applicants[currentApplicantIndex], currentApplicantIndex);

            container.innerHTML = html;

            // Add event listeners for tabs
            if (applicationData.totalApplicants > 1) {
                document.querySelectorAll('.applicant-tab').forEach(tab => {
                    tab.addEventListener('click', function() {
                        const applicantIndex = parseInt(this.getAttribute('data-applicant'));
                        switchApplicant(applicantIndex);
                    });
                });
            }

            // Add copy functionality to all copy fields
            setupCopyFunctionality();

            // Add step click functionality
            setupStepNavigation();
        }

        // Switch between applicants - FIXED VERSION
        function switchApplicant(applicantIndex) {
            currentApplicantIndex = applicantIndex;
            renderApplication();

            // Also update the step highlighting for the new applicant
            setTimeout(() => {
                setupStepNavigation();
                highlightStepSection(0); // Highlight first section by default
            }, 100);
        }

        // ... rest of your functions remain exactly the same (renderApplicant, renderField, setupCopyFunctionality, etc.)
        // Render a single applicant
        function renderApplicant(applicant, index) {
            return `
            <div class="bg-white rounded-xl shadow-lg overflow-hidden fade-in">
                <div class="border-b border-gray-200 px-6 py-4 bg-gray-50">
                    <h3 class="text-xl font-bold text-gray-800 flex items-center">
                        <i class="fas fa-user mr-2 text-blue-500"></i>
                        Applicant ${index + 1} - ${applicant.user_pnr ?? applicant.id}
                        ${applicant.completed ? 
                            '<span class="ml-2 bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">Completed</span>' : 
                            '<span class="ml-2 bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full">In Progress</span>'
                        }
                    </h3>
                </div>
                
                <!-- Step Progress -->
                <div class="step-progress">
                    <div class="step-item" data-step="0">
                        <div class="step-icon active">
                            <i class="fas fa-passport"></i>
                        </div>
                        <div class="step-label active">Passport</div>
                    </div>
                    <div class="step-item" data-step="1">
                        <div class="step-icon active">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <div class="step-label active">NID</div>
                    </div>
                    <div class="step-item" data-step="2">
                        <div class="step-icon active">
                            <i class="fas fa-address-book"></i>
                        </div>
                        <div class="step-label active">Contact</div>
                    </div>
                    <div class="step-item" data-step="3">
                        <div class="step-icon active">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="step-label active">Family</div>
                    </div>
                    <div class="step-item" data-step="4">
                        <div class="step-icon active">
                            <i class="fas fa-hotel"></i>
                        </div>
                        <div class="step-label active">Accommodation</div>
                    </div>
                    <div class="step-item" data-step="5">
                        <div class="step-icon active">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <div class="step-label active">Employment</div>
                    </div>
                    <div class="step-item" data-step="6">
                        <div class="step-icon active">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="step-label active">Income</div>
                    </div>
                    <div class="step-item" data-step="7">
                        <div class="step-icon active">
                            <i class="fas fa-plane"></i>
                        </div>
                        <div class="step-label active">Travel</div>
                    </div>
                    <div class="step-item" data-step="8">
                        <div class="step-icon active">
                            <i class="fas fa-globe-americas"></i>
                        </div>
                        <div class="step-label active">Travel History</div>
                    </div>
                </div>
                
                <div class="p-6 space-y-6">
                    <!-- Passport Information -->
                    <div id="passport-info" class="applicant-section p-4 rounded-lg">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-passport mr-2 text-blue-500"></i>
                            Passport Information
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            ${renderField('Given Name', applicant.passportInfo?.pp_given_name)}
                            ${renderField('Family Name', applicant.passportInfo?.pp_family_name)}
                            ${renderField('Gender', applicant.passportInfo?.pp_gender)}
                            ${renderField('Place of Birth', applicant.passportInfo?.pp_pob)}
                            ${renderField('Date of Birth', applicant.passportInfo?.pp_dob)}
                            ${renderField('Passport Number', applicant.passportInfo?.pp_number)}
                            ${renderField('Issuing Authority', applicant.passportInfo?.pp_issuing_authority)}
                            ${renderField('Issue Date', applicant.passportInfo?.pp_issue_date)}
                            ${renderField('Expiry Date', applicant.passportInfo?.pp_expiry_date)}
                        </div>
                    </div>

                    <!-- NID Information -->
                    <div id="nid-info" class="applicant-section p-4 rounded-lg">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-id-card mr-2 text-green-500"></i>
                            NID Information
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            ${renderField('Has NID', applicant.nidInfo?.has_nid !== null ? (applicant.nidInfo?.has_nid ? 'Yes' : 'No') : 'Not provided')}
                            ${applicant.nidInfo?.has_nid ? renderField('NID Number', applicant.nidInfo?.nid_number) : ''}
                            ${applicant.nidInfo?.has_nid ? renderField('Issuing Authority', applicant.nidInfo?.nid_issuing_authority) : ''}
                            ${applicant.nidInfo?.has_nid && applicant.nidInfo?.nid_isue_date ? renderField('Issue Date', applicant.nidInfo?.nid_isue_date) : ''}
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div id="contact-info" class="applicant-section p-4 rounded-lg">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-address-book mr-2 text-purple-500"></i>
                            Contact Information
                        </h4>
                        <div class="space-y-4">
                            <!-- Emails -->
                            <div>
                                <h5 class="font-medium text-gray-700 mb-2">Email Addresses</h5>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    ${(applicant.contactInfo?.emails || []).filter(email => email).map(email => 
                                        renderField('Email', email)
                                    ).join('')}
                                </div>
                            </div>
                            
                            <!-- Phones -->
                            <div>
                                <h5 class="font-medium text-gray-700 mb-2">Phone Numbers</h5>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    ${(applicant.contactInfo?.phones || []).filter(phone => phone).map(phone => 
                                        renderField('Phone', phone)
                                    ).join('')}
                                </div>
                            </div>
                            
                            <!-- Preferred Phone -->
                            ${applicant.contactInfo?.preferred_phone_no ? `
                                <div class="grid grid-cols-1">
                                    ${renderField('Preferred Phone', applicant.contactInfo.preferred_phone_no)}
                                </div>
                            ` : ''}
                            
                            <!-- Addresses -->
                            <div>
                                <h5 class="font-medium text-gray-700 mb-2">Addresses</h5>
                                <div class="space-y-3">
                                    ${(applicant.contactInfo?.addresses || []).map((address, addrIndex) => `
                                        <div class="border border-gray-200 rounded-lg p-3 bg-white">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                                ${renderField('Line 1', address.line1)}
                                                ${renderField('Line 2', address.line2)}
                                                ${renderField('City', address.city)}
                                                ${renderField('State', address.state)}
                                                ${renderField('Postal Code', address.postalCode)}
                                                ${renderField('Lived In For', address.livedInFor)}
                                                ${renderField('Ownership Status', address.ownershipStatus)}
                                                ${renderField('Correspondence Address', address.isCorrespondence ? 'Yes' : 'No')}
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Family Information -->
                    <div id="family-info" class="applicant-section p-4 rounded-lg">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-users mr-2 text-pink-500"></i>
                            Family Information
                        </h4>
                        <div class="space-y-4">
                            ${renderField('Relationship Status', applicant.familyInfo?.relationshipStatus)}
                            ${renderField('Has Relative in UK', applicant.familyInfo?.hasRelativeInUK !== null ? (applicant.familyInfo?.hasRelativeInUK ? 'Yes' : 'No') : 'Not provided')}
                            
                            <!-- Family Members -->
                            ${applicant.familyInfo?.familyMembers && applicant.familyInfo.familyMembers.length > 0 ? `
                                <div>
                                    <h5 class="font-medium text-gray-700 mb-2">Family Members</h5>
                                    <div class="space-y-3">
                                        ${applicant.familyInfo.familyMembers.map((member, memberIndex) => `
                                            <div class="border border-gray-200 rounded-lg p-3 bg-white">
                                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                                                    ${renderField('Relation', member.relation)}
                                                    ${renderField('Given Name', member.givenName)}
                                                    ${renderField('Family Name', member.familyName)}
                                                    ${renderField('Date of Birth', member.dob)}
                                                    ${renderField('Nationality', member.nationality)}
                                                    ${renderField('Lives With You', member.liveWith ? 'Yes' : 'No')}
                                                    ${renderField('Travelling to UK', member.travellingUK ? 'Yes' : 'No')}
                                                    ${member.travellingUK ? renderField('Passport Number', member.passportNo) : ''}
                                                </div>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    </div>

                    <!-- Accommodation Details -->
                    <div id="accommodation-info" class="applicant-section p-4 rounded-lg">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-hotel mr-2 text-yellow-500"></i>
                            Accommodation Details
                        </h4>
                        <div class="space-y-4">
                            ${renderField('Has UK Address', applicant.accommodationDetails?.hasAddress !== null ? (applicant.accommodationDetails?.hasAddress ? 'Yes' : 'No') : 'Not provided')}
                            
                            <!-- Accommodation Addresses -->
                            <div>
                                <h5 class="font-medium text-gray-700 mb-2">Accommodation Addresses</h5>
                                <div class="space-y-3">
                                    ${(applicant.accommodationDetails?.addresses || []).map((address, addrIndex) => `
                                        <div class="border border-gray-200 rounded-lg p-3 bg-white">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                                ${renderField('Hotel Name', applicant.accommodationDetails?.hotels?.[addrIndex])}
                                                ${renderField('Line 1', address.line1)}
                                                ${renderField('Line 2', address.line2)}
                                                ${renderField('City', address.city)}
                                                ${renderField('State', address.state)}
                                                ${renderField('Postal Code', address.postalCode)}
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Employment Information -->
                    <div id="employment-info" class="applicant-section p-4 rounded-lg">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-briefcase mr-2 text-orange-500"></i>
                            Employment Information
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            ${renderField('Employment Status', applicant.employmentInfo?.employmentStatus)}
                            ${applicant.employmentInfo?.employmentStatus === 'self-employed' ? renderField('Job Details', applicant.employmentInfo?.jobDetails) : ''}
                            ${applicant.employmentInfo?.employmentStatus === 'self-employed' ? renderField('Yearly Earning', applicant.employmentInfo?.yearlyEarning) : ''}
                            ${applicant.employmentInfo?.employmentStatus === 'employed' ? renderField('Job Title', applicant.employmentInfo?.jobTitle) : ''}
                            ${applicant.employmentInfo?.employmentStatus === 'employed' ? renderField('Monthly Income', applicant.employmentInfo?.monthlyIncome) : ''}
                            ${applicant.employmentInfo?.jobDescription ? renderField('Job Description', applicant.employmentInfo?.jobDescription) : ''}
                        </div>
                    </div>

                    <!-- Income & Expenditure -->
                    <div id="income-info" class="applicant-section p-4 rounded-lg">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-chart-line mr-2 text-indigo-500"></i>
                            Income & Expenditure
                        </h4>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                ${renderField('Has Savings', applicant.incomeExpenditure?.haveSavings !== null ? (applicant.incomeExpenditure?.haveSavings ? 'Yes' : 'No') : 'Not provided')}
                                ${renderField('Planning to Expense', applicant.incomeExpenditure?.planningToExpense)}
                                ${renderField('Total Monthly Expense', applicant.incomeExpenditure?.totalExpenseInBd)}
                            </div>
                            
                            <!-- Payment Information -->
                            ${applicant.incomeExpenditure?.paymentInfo && applicant.incomeExpenditure.paymentInfo.some(p => p.currency || p.amount || p.paidFor) ? `
                                <div>
                                    <h5 class="font-medium text-gray-700 mb-2">Payment Information</h5>
                                    <div class="space-y-3">
                                        ${applicant.incomeExpenditure.paymentInfo.map((payment, paymentIndex) => 
                                            payment.currency || payment.amount || payment.paidFor ? `
                                                <div class="border border-gray-200 rounded-lg p-3 bg-white">
                                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                                                        ${renderField('Currency', payment.currency)}
                                                        ${renderField('Amount', payment.amount)}
                                                        ${renderField('Paid For', payment.paidFor)}
                                                    </div>
                                                </div>
                                            ` : ''
                                        ).join('')}
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    </div>

                    <!-- Travel Information -->
                    <div id="travel-info" class="applicant-section p-4 rounded-lg">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-plane mr-2 text-teal-500"></i>
                            Travel Information
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            ${renderField('Visit Reason', applicant.travelInfo?.visitMainReason)}
                            ${applicant.travelInfo?.businessReasonToVisitUk ? renderField('Business Reason', applicant.travelInfo?.businessReasonToVisitUk) : ''}
                            ${applicant.travelInfo?.tourismReasonToVisitUk ? renderField('Tourism Reason', applicant.travelInfo?.tourismReasonToVisitUk) : ''}
                            ${renderField('Activities', applicant.travelInfo?.activities)}
                            ${renderField('Arrival Date', applicant.travelInfo?.arrivalDate)}
                            ${renderField('Departure Date', applicant.travelInfo?.leaveDate)}
                        </div>
                    </div>

                    <!-- Travel History -->
                    <div id="travel-history" class="applicant-section p-4 rounded-lg">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-globe-americas mr-2 text-red-500"></i>
                            Travel History
                        </h4>
                        <div class="grid grid-cols-1">
                            ${renderField('Travel History', applicant.travelHistory?.history || 'Not provided', true)}
                        </div>
                    </div>
                </div>
            </div>
        `;
        }

        // Render a field with copy functionality
        function renderField(label, value, isTextarea = false) {
            if (!value && value !== 0 && value !== false) {
                value = 'Not provided';
            }

            if (value === true) value = 'Yes';
            if (value === false) value = 'No';

            const displayValue = String(value).trim();
            const fieldId = `field-${Math.random().toString(36).substr(2, 9)}`;

            if (isTextarea) {
                return `
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">${label}</label>
                    <div id="${fieldId}" class="copy-field p-3 border border-gray-200 rounded bg-white min-h-[100px] whitespace-pre-wrap" data-value="${displayValue.replace(/"/g, '&quot;')}">
                        ${displayValue}
                    </div>
                </div>
            `;
            }

            return `
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">${label}</label>
                <div id="${fieldId}" class="copy-field p-2 border border-gray-200 rounded bg-white truncate" data-value="${displayValue.replace(/"/g, '&quot;')}" title="Click to copy: ${displayValue}">
                    ${displayValue}
                </div>
            </div>
        `;
        }

        // Setup copy functionality
        function setupCopyFunctionality() {
            const copyFields = document.querySelectorAll('.copy-field');

            copyFields.forEach(field => {
                field.addEventListener('click', function() {
                    const value = this.getAttribute('data-value');
                    copyToClipboard(value, this);
                });
            });
        }

        // Setup step navigation
        function setupStepNavigation() {
            const stepItems = document.querySelectorAll('.step-item');

            stepItems.forEach((step, index) => {
                step.addEventListener('click', function() {
                    const stepIndex = parseInt(this.getAttribute('data-step'));
                    highlightStepSection(stepIndex);
                });
            });
        }

        // Highlight step section
        function highlightStepSection(stepIndex) {
            // Remove all highlights
            document.querySelectorAll('.applicant-section').forEach(section => {
                section.classList.remove('highlighted');
            });

            document.querySelectorAll('.step-icon').forEach(icon => {
                icon.classList.remove('highlighted');
            });

            document.querySelectorAll('.step-label').forEach(label => {
                label.classList.remove('highlighted');
            });

            // Add highlight to selected step
            const stepElement = document.querySelector(`.step-item[data-step="${stepIndex}"]`);
            if (stepElement) {
                stepElement.querySelector('.step-icon').classList.add('highlighted');
                stepElement.querySelector('.step-label').classList.add('highlighted');
            }

            // Highlight corresponding section
            const sectionId = stepSections[stepIndex];
            const sectionElement = document.getElementById(sectionId);
            if (sectionElement) {
                sectionElement.classList.add('highlighted');

                // Scroll to section
                sectionElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        }

        // Copy text to clipboard - GUARANTEED WORKING VERSION
        function copyToClipboard(text, element) {
            // Create a temporary textarea element
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.left = '-999999px';
            textarea.style.top = '-999999px';
            document.body.appendChild(textarea);

            // Select and copy the text
            textarea.focus();
            textarea.select();

            try {
                const successful = document.execCommand('copy');
                document.body.removeChild(textarea);

                if (successful) {
                    // Show visual feedback
                    element.classList.add('copied');

                    // Show toast
                    showToast('Copied to clipboard!');

                    // Remove copied class after 1 second
                    setTimeout(() => {
                        element.classList.remove('copied');
                    }, 1000);
                } else {
                    showToast('Failed to copy to clipboard');
                }
            } catch (err) {
                document.body.removeChild(textarea);
                console.error('Failed to copy: ', err);
                showToast('Failed to copy to clipboard');
            }
        }

        // Show toast notification
        function showToast(message) {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toast-message');

            toastMessage.textContent = message;
            toast.classList.remove('hidden');
            toast.classList.add('show');

            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    toast.classList.add('hidden');
                }, 300);
            }, 2000);
        }

        // Show error message
        function showError(message) {
            const container = document.getElementById('application-container');
            container.innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                <h3 class="text-xl font-semibold text-red-800 mb-2">Error</h3>
                <p class="text-red-600">${message}</p>
                <button onclick="window.location.href='index.php'" class="mt-4 bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg transition duration-300">
                    Back to Dashboard
                </button>
            </div>
        `;
        }

        // Download JSON
        function downloadJSON() {
            if (!applicationData) return;

            const dataStr = JSON.stringify(applicationData, null, 2);
            const dataBlob = new Blob([dataStr], {
                type: 'application/json'
            });

            const link = document.createElement('a');
            link.href = URL.createObjectURL(dataBlob);
            link.download = `uk-visa-application-${applicationData.pnr}.json`;
            link.click();

            showToast('JSON file downloaded!');
        }
    </script>
</body>

</html>