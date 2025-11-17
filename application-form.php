<?php
require 'server/db_connection.php';

$pnr = $_GET['pnr'] ?? null;
$dbApplicationData = null;

if ($pnr) {
    // 1. Fetch application info from DATABASE FIRST
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE pnr = ?");
    $stmt->execute([$pnr]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($application) {
        // 2. Fetch all applicants from DATABASE
        $stmt2 = $pdo->prepare("SELECT * FROM applicants WHERE pnr = ?");
        $stmt2->execute([$pnr]);
        $appRows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $applicants = [];
        foreach ($appRows as $ap) {
            $applicants[] = [
                "id" => $ap['user_pnr'],
                "pnr" => $ap['pnr'],
                "user_pnr" => $ap['user_pnr'],
                "completed" => (bool)$ap['completed'],
                "passportInfo" => json_decode($ap['passport_info'], true) ?? [],
                "nidInfo" => json_decode($ap['nid_info'], true) ?? [],
                "contactInfo" => json_decode($ap['contact_info'], true) ?? [],
                "familyInfo" => json_decode($ap['family_info'], true) ?? [],
                "accommodationDetails" => json_decode($ap['accommodation_details'], true) ?? [],
                "employmentInfo" => json_decode($ap['employment_info'], true) ?? [],
                "incomeExpenditure" => json_decode($ap['income_expenditure'], true) ?? [],
                "travelInfo" => json_decode($ap['travel_info'], true) ?? [],
                "travelHistory" => json_decode($ap['travel_history'], true) ?? []
            ];
        }

        // 3. Prepare DB data for JS
        $dbApplicationData = [
            'pnr' => $application['pnr'],
            'nameOfApplicant' => $applicants[0]['passportInfo']['pp_family_name'] ?? '',
            'totalApplicants' => count($applicants),
            'applicants' => $applicants,
            'currentApplicant' => 0,
            'currentStep' => 0,
            'timestamp' => $application['created_at'],
            'source' => 'database'
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UK Visa Application</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* আপনার existing CSS styles এখানে রাখুন */
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .step {
            display: none;
        }
        
        .step.active {
            display: block;
        }
        
        .tab {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .tab.active {
            background-color: #3b82f6;
            color: white;
        }
        
        .progress-bar {
            transition: width 0.5s ease-in-out;
        }
        
        .form-section {
            border-left: 4px solid #3b82f6;
        }
        
        .summary-item {
            border-bottom: 1px solid #e5e7eb;
            padding: 12px 0;
        }
        
        .applicant-progress {
            height: 6px;
            border-radius: 3px;
        }
        
        .applicant-complete {
            background-color: #10b981;
        }
        
        .applicant-incomplete {
            background-color: #d1d5db;
        }
        
        .step-nav-item {
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .step-nav-item:hover {
            background-color: #f3f4f6;
        }
        
        .step-nav-item.active {
            border-left-color: #3b82f6;
            background-color: #eff6ff;
        }
        
        .step-nav-item.completed .step-icon {
            background-color: #10b981;
            color: white;
        }
        
        .step-nav-item.current .step-icon {
            background-color: #3b82f6;
            color: white;
        }
        
        .dynamic-field-group {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: #f9fafb;
        }
        
        .address-group {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: #f9fafb;
        }
        
        .family-member-group {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: #f9fafb;
        }
        
        .travel-history-group {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: #f9fafb;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <!-- Header -->
        <header class="text-center mb-12">
            <div class="flex items-center justify-center mb-4">
                <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-passport text-white text-xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-gray-800">UK Visa Application</h1>
            </div>
            <p class="text-gray-600 max-w-2xl mx-auto">Complete your UK visa application form. Please ensure all information is accurate and matches your official documents.</p>
        </header>

        <!-- Main Application Container -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <!-- Initial Screen -->
            <div id="initial-screen" class="p-8">
                <div class="max-w-md mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">How many applicants are under the same PNR?</h2>
                    <div class="mb-8">
                        <label for="applicant-count" class="block text-gray-700 mb-2">Number of Applicants</label>
                        <select id="applicant-count" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="1">1 Applicant</option>
                            <option value="2">2 Applicants</option>
                            <option value="3">3 Applicants</option>
                            <option value="4">4 Applicants</option>
                            <option value="5">5 Applicants</option>
                        </select>
                    </div>
                    <button id="start-application" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-lg transition duration-300">
                        Start Application
                    </button>
                    
                    <!-- Load Saved Application -->
                    <div id="saved-application-section" class="mt-8 p-4 bg-yellow-50 rounded-lg border border-yellow-200 hidden">
                        <h3 class="font-medium text-yellow-800 mb-2">Saved Application Found</h3>
                        <p class="text-yellow-700 text-sm mb-3">We found a saved application with PNR: <span id="saved-pnr" class="font-mono font-bold"></span></p>
                        <button id="load-application" class="w-full bg-yellow-500 hover:bg-yellow-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300">
                            Load Saved Application
                        </button>
                    </div>
                </div>
            </div>

            <!-- Multi-Applicant Form (Hidden Initially) -->
            <div id="multi-applicant-form" class="hidden">
                <!-- PNR Display -->
                <div class="px-8 pt-8 flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">Application PNR: <span id="pnr-display" class="font-mono text-blue-600"></span></h2>
                        <p class="text-gray-600 text-sm">Your application is automatically saved as you progress</p>
                    </div>
                    <div class="flex space-x-2">
                        <button id="back-to-dashboard" class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300 text-sm">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                        </button>
                        <button id="save-exit" class="bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300 text-sm">
                            <i class="fas fa-save mr-2"></i>Save & Exit
                        </button>
                    </div>
                </div>

                <!-- Overall Progress -->
                <div class="px-8 pt-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-sm font-medium text-blue-600">Overall Progress</span>
                        <span class="text-sm font-medium text-gray-500"><span id="completed-applicants">0</span> of <span id="total-applicants">1</span> applicants completed</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5 mb-6">
                        <div id="overall-progress-bar" class="bg-blue-600 h-2.5 rounded-full progress-bar" style="width: 0%"></div>
                    </div>
                </div>

                <!-- Applicant Tabs with Individual Progress -->
                <div id="applicant-tabs" class="flex overflow-x-auto border-b border-gray-200 px-8">
                    <!-- Tabs will be dynamically generated here -->
                </div>

                <!-- Current Applicant Progress -->
                <div class="px-8 pt-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-sm font-medium text-gray-700">Applicant <span id="current-applicant-number">1</span> Progress</span>
                        <span class="text-sm font-medium text-gray-500"><span id="current-step">1</span> of <span id="total-steps">9</span></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div id="individual-progress-bar" class="bg-green-600 h-2.5 rounded-full progress-bar" style="width: 11.11%"></div>
                    </div>
                </div>

                <!-- Main Content Area -->
                <div class="flex flex-col md:flex-row p-8">
                    <!-- Step Navigation Sidebar -->
                    <div class="w-full md:w-1/4 mb-6 md:mb-0 md:pr-6">
                        <div class="bg-gray-50 rounded-lg p-4 sticky top-4">
                            <h3 class="font-bold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-list-ol mr-2 text-blue-500"></i>
                                Application Steps
                            </h3>
                            <div id="step-navigation" class="space-y-2">
                                <!-- Step navigation items will be dynamically generated here -->
                            </div>
                        </div>
                    </div>

                    <!-- Form Steps -->
                    <div id="form-steps" class="w-full md:w-3/4">
                        <!-- Steps will be dynamically generated here -->
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="flex justify-between px-8 pb-8">
                    <button id="prev-btn" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-6 rounded-lg transition duration-300 flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i> Previous
                    </button>
                    <div class="flex space-x-4">
                        <button id="next-applicant-btn" class="hidden bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-6 rounded-lg transition duration-300 flex items-center">
                            Save & Next Applicant <i class="fas fa-user-plus ml-2"></i>
                        </button>
                        <button id="next-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition duration-300 flex items-center">
                            Save & Next Step <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                    <button id="submit-btn" class="hidden bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-6 rounded-lg transition duration-300">
                        Submit Application
                    </button>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="mt-12 text-center text-gray-500 text-sm">
            <p>© 2025 TravHub Global Limited. All rights reserved.</p>
            <p class="mt-2">This is a demonstration form only. For official visa applications, visit the <a href="#" class="text-blue-600 hover:underline">GOV.UK website</a>.</p>
        </footer>
    </div>

    <script>
        // Application state
        const state = {
            currentApplicant: 0,
            currentStep: 0,
            totalSteps: 9,
            totalApplicants: 1,
            pnr: '',
            applicants: [],
            steps: [
                { 
                    name: 'Passport Information', 
                    icon: 'fa-passport',
                    description: 'Provide your passport details'
                },
                { 
                    name: 'NID Information', 
                    icon: 'fa-id-card',
                    description: 'National Identity Card details'
                },
                { 
                    name: 'Contact Information', 
                    icon: 'fa-address-book',
                    description: 'Your contact details'
                },
                { 
                    name: 'Family Information', 
                    icon: 'fa-users',
                    description: 'Information about your family'
                },
                { 
                    name: 'Accommodation Details', 
                    icon: 'fa-hotel',
                    description: 'Where you will stay in the UK'
                },
                { 
                    name: 'Employment Information', 
                    icon: 'fa-briefcase',
                    description: 'Your employment details'
                },
                { 
                    name: 'Income & Expenditure', 
                    icon: 'fa-chart-line',
                    description: 'Financial information'
                },
                { 
                    name: 'Travel Information', 
                    icon: 'fa-plane',
                    description: 'Your travel plans'
                },
                { 
                    name: 'Travel History', 
                    icon: 'fa-globe-americas',
                    description: 'Previous travel history'
                }
            ]
        };

        // Check for URL parameters on page load - MODIFIED VERSION
        function checkURLParameters() {
            const urlParams = new URLSearchParams(window.location.search);
            const pnr = urlParams.get('pnr');
            
            if (pnr) {
                // FIRST: Try to load from DATABASE (if PHP found data)
                <?php if ($dbApplicationData): ?>
                    const dbApplicationData = <?php echo json_encode($dbApplicationData); ?>;
                    if (loadApplicationFromDB(dbApplicationData)) {
                        console.log('Application loaded from DATABASE');
                        return;
                    }
                <?php endif; ?>

                // SECOND: If no DB data, try localStorage
                if (loadApplicationByPNR(pnr)) {
                    console.log('Application loaded from LOCALSTORAGE');
                    return;
                }

                // THIRD: If nothing found, show error
                alert(`Application with PNR ${pnr} not found in Database or LocalStorage. Starting a new application.`);
            }
        }

        // Load application from DB data
        function loadApplicationFromDB(applicationData) {
            if (applicationData && applicationData.pnr) {
                // Restore state from DB data
                state.totalApplicants = applicationData.totalApplicants;
                state.pnr = applicationData.pnr;
                state.applicants = applicationData.applicants;
                state.currentApplicant = applicationData.currentApplicant || 0;
                state.currentStep = applicationData.currentStep || 0;
                
                // Hide initial screen and show form directly
                document.getElementById('initial-screen').classList.add('hidden');
                document.getElementById('multi-applicant-form').classList.remove('hidden');
                
                // Display PNR
                document.getElementById('pnr-display').textContent = state.pnr;
                document.getElementById('total-applicants').textContent = state.totalApplicants;
                
                // Generate tabs
                generateTabs();
                
                // Generate step navigation
                generateStepNavigation();
                
                // Generate form steps for the current applicant
                generateFormSteps();
                
                // Update UI
                updateUI();
                
                return true;
            }
            return false;
        }

        // Load specific application by PNR (for localStorage)
        function loadApplicationByPNR(pnr) {
            const savedApplication = localStorage.getItem('ukVisaApplication-'+pnr);
            
            if (savedApplication) {
                const applicationData = JSON.parse(savedApplication);
                
                if (applicationData.pnr === pnr) {
                    // Restore state from saved data
                    state.totalApplicants = applicationData.totalApplicants;
                    state.pnr = applicationData.pnr;
                    state.applicants = applicationData.applicants;
                    state.currentApplicant = applicationData.currentApplicant || 0;
                    state.currentStep = applicationData.currentStep || 0;
                    
                    // Hide initial screen and show form directly
                    document.getElementById('initial-screen').classList.add('hidden');
                    document.getElementById('multi-applicant-form').classList.remove('hidden');
                    
                    // Display PNR
                    document.getElementById('pnr-display').textContent = state.pnr;
                    document.getElementById('total-applicants').textContent = state.totalApplicants;
                    
                    // Generate tabs
                    generateTabs();
                    
                    // Generate step navigation
                    generateStepNavigation();
                    
                    // Generate form steps for the current applicant
                    generateFormSteps();
                    
                    // Update UI
                    updateUI();
                    
                    return true;
                }
            }
            
            return false;
        }

        // Check if there's a saved application in localStorage
        function checkForSavedApplication() {
            // ---------------------------------------------------------
            // Find LAST SAVED Application from localStorage
            // ---------------------------------------------------------
            let lastApplication = null;
            let latestTimestamp = 0;

            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                
                if (key.startsWith('ukVisaApplication-')) {
                    try {
                        const storedValue = JSON.parse(localStorage.getItem(key));
                        
                        // Make sure object has timestamp
                        if (storedValue && storedValue.timestamp) {
                            const ts = new Date(storedValue.timestamp).getTime();
                            
                            if (ts > latestTimestamp) {
                                latestTimestamp = ts;
                                lastApplication = storedValue;
                            }
                        }
                    } catch (e) {
                        console.error('Error parsing localStorage item:', key, e);
                    }
                }
            }

            // ---------------------------------------------------------
            // Load last application if exists
            // ---------------------------------------------------------
            if (lastApplication) {
                document.getElementById('saved-pnr').textContent = lastApplication.pnr;
                document.getElementById('saved-application-section').classList.remove('hidden');
            } else {
                console.log('No UK Visa Application found.');
            }
        }

        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            // Set up event listeners
            document.getElementById('start-application').addEventListener('click', startApplication);
            document.getElementById('load-application').addEventListener('click', loadSavedApplication);
            document.getElementById('prev-btn').addEventListener('click', previousStep);
            document.getElementById('next-btn').addEventListener('click', nextStep);
            document.getElementById('next-applicant-btn').addEventListener('click', nextApplicant);
            document.getElementById('submit-btn').addEventListener('click', submitApplication);
            document.getElementById('save-exit').addEventListener('click', saveAndExit);
            document.getElementById('back-to-dashboard').addEventListener('click', function() {
                window.location.href = 'index.php';
            });
            
            // Check for URL parameters first - WITH DB PRIORITY
            checkURLParameters();
            
            // Check for saved applications in localStorage (for initial screen)
            checkForSavedApplication();
        });

        // Load saved application from localStorage
        function loadSavedApplication() {
            // 1. Get the PNR shown in UI
            const pnr = document.getElementById('saved-pnr').textContent.trim();
            if (!pnr) {
                console.error("No PNR found.");
                return;
            }

            // 2. Load from localStorage
            const savedApplication = localStorage.getItem('ukVisaApplication-' + pnr);
            if (!savedApplication) {
                console.error("No saved application found for PNR:", pnr);
                return;
            }

            // 3. Parse it
            const applicationData = JSON.parse(savedApplication);

            // 4. Restore state
            state.totalApplicants = applicationData.totalApplicants;
            state.pnr = applicationData.pnr;
            state.applicants = applicationData.applicants;
            state.currentApplicant = applicationData.currentApplicant || 0;
            state.currentStep = applicationData.currentStep || 0;

            // 5. Show UI
            document.getElementById('initial-screen').classList.add('hidden');
            document.getElementById('multi-applicant-form').classList.remove('hidden');

            // 6. Display PNR
            document.getElementById('pnr-display').textContent = state.pnr;
            document.getElementById('total-applicants').textContent = state.totalApplicants;

            // 7. Rebuild UI
            generateTabs();
            generateStepNavigation();
            generateFormSteps();
            updateUI();
        }

        // Start the application process
        function startApplication() {
            const applicantCount = parseInt(document.getElementById('applicant-count').value);
            state.totalApplicants = applicantCount;
            
            // Generate PNR
            state.pnr = generatePNR();
            
            // Initialize all applicants
            for (let i = 0; i < applicantCount; i++) {
                initializeApplicant(i);
            }
            
            // Hide initial screen and show form
            document.getElementById('initial-screen').classList.add('hidden');
            document.getElementById('multi-applicant-form').classList.remove('hidden');
            
            // Display PNR
            document.getElementById('pnr-display').textContent = state.pnr;
            document.getElementById('total-applicants').textContent = state.totalApplicants;
            
            // Generate tabs
            generateTabs();
            
            // Generate step navigation
            generateStepNavigation();
            
            // Generate form steps for the first applicant
            generateFormSteps();
            
            // Update UI
            updateUI();
            
            // Save initial state
            saveToLocalStorage();
        }

        // Generate a unique PNR
        function generatePNR() {
            const timestamp = Date.now().toString().slice(-6);
            const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
            return `TRH-UK-PNR-${timestamp}K${random}`;
        }

        // Generate applicant ID based on PNR
        function generateApplicantId(applicantIndex) {
            return `${state.pnr}-APPT-${(applicantIndex + 1).toString().padStart(3, '0')}`;
        }

        // Initialize an applicant with empty data
        function initializeApplicant(index) {
            state.applicants[index] = {
                id: generateApplicantId(index),
                pnr: state.pnr,
                user_pnr: generateApplicantId(index),
                completed: false,
                passportInfo: {},
                nidInfo: {
                    has_nid: null
                },
                contactInfo: {
                    emails: [''],
                    phones: [''],
                    addresses: [{
                        line1: '',
                        line2: '',
                        city: '',
                        state: '',
                        postalCode: '',
                        isCorrespondence: false,
                        livedInFor: '',
                        ownershipStatus: ''
                    }],
                    preferred_phone_no: ''
                },
                familyInfo: {
                    relationshipStatus: '',
                    familyMembers: [],
                    hasRelativeInUK: null,
                    relativeAddress: {
                        line1: '',
                        line2: '',
                        city: '',
                        state: '',
                        postalCode: ''
                    }
                },
                accommodationDetails: {
                    hasAddress: null,
                    hotels: [''],
                    addresses: [{
                        line1: '',
                        line2: '',
                        city: '',
                        state: '',
                        postalCode: ''
                    }]
                },
                employmentInfo: {
                    employmentStatus: '',
                    jobDetails: '',
                    yearlyEarning: '',
                    jobTitle: '',
                    monthlyIncome: '',
                    jobDescription: ''
                },
                incomeExpenditure: {
                    haveSavings: null,
                    planningToExpense: '',
                    totalExpenseInBd: '',
                    paymentInfo: [{
                        currency: '',
                        amount: '',
                        paidFor: ''
                    }]
                },
                travelInfo: {
                    visitMainReason: '',
                    businessReasonToVisitUk: '',
                    tourismReasonToVisitUk: '',
                    activities: '',
                    arrivalDate: '',
                    leaveDate: ''
                },
                travelHistory: []
            };
        }

        // Generate tabs for each applicant with progress indicators
        function generateTabs() {
            const tabsContainer = document.getElementById('applicant-tabs');
            tabsContainer.innerHTML = '';
            
            for (let i = 0; i < state.totalApplicants; i++) {
                const applicant = state.applicants[i];
                const completedSteps = countCompletedSteps(i);
                const progressPercentage = (completedSteps / state.totalSteps) * 100;
                
                const tab = document.createElement('div');
                tab.className = `tab py-3 px-6 text-sm font-medium flex flex-col items-center min-w-32 ${i === state.currentApplicant ? 'active bg-blue-600 text-white' : 'text-gray-500 hover:text-gray-700'}`;
                tab.dataset.applicant = i;
                
                tab.innerHTML = `
                    <div class="flex justify-between w-full items-center mb-1">
                        <span>Applicant ${i + 1} &nbsp;</span> 
                        ${applicant.completed ? '<i class="fas fa-check-circle text-green-500"></i>' : ''}
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-1.5 applicant-progress">
                        <div class="h-1.5 rounded-full ${applicant.completed ? 'applicant-complete' : 'applicant-incomplete'}" style="width: ${progressPercentage}%"></div>
                    </div>
                    <div class="text-xs mt-1">${completedSteps}/${state.totalSteps}</div>
                `;
                
                tab.addEventListener('click', function() {
                    switchApplicant(parseInt(this.dataset.applicant));
                });
                
                tabsContainer.appendChild(tab);
            }
        }

        // Generate step navigation sidebar
        function generateStepNavigation() {
            const stepNavContainer = document.getElementById('step-navigation');
            stepNavContainer.innerHTML = '';
            
            state.steps.forEach((step, index) => {
                const isCompleted = isStepCompleted(index);
                const isCurrent = index === state.currentStep;
                
                const stepNavItem = document.createElement('div');
                stepNavItem.className = `step-nav-item p-3 rounded-lg ${isCurrent ? 'active current' : ''} ${isCompleted ? 'completed' : ''}`;
                stepNavItem.dataset.step = index;
                
                stepNavItem.innerHTML = `
                    <div class="flex items-center">
                        <div class="step-icon w-8 h-8 rounded-full flex items-center justify-center mr-3 ${isCompleted ? 'bg-green-500 text-white' : isCurrent ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-600'}">
                            <i class="fas ${step.icon} text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <div class="font-medium text-gray-800">${step.name}</div>
                            <div class="text-xs text-gray-500">${step.description}</div>
                        </div>
                        ${isCompleted ? '<i class="fas fa-check text-green-500 ml-2"></i>' : ''}
                    </div>
                `;
                
                stepNavItem.addEventListener('click', function() {
                    const stepIndex = parseInt(this.dataset.step);
                    jumpToStep(stepIndex);
                });
                
                stepNavContainer.appendChild(stepNavItem);
            });
        }

        // Check if a step is completed for the current applicant
        function isStepCompleted(stepIndex) {
            const applicant = state.applicants[state.currentApplicant];
            
            switch(stepIndex) {
                case 0: // Passport Information
                    return applicant.passportInfo.pp_given_name && 
                           applicant.passportInfo.pp_family_name &&
                           applicant.passportInfo.pp_number;
                case 1: // NID Information
                    return applicant.nidInfo.has_nid !== null;
                case 2: // Contact Information
                    return applicant.contactInfo.emails[0] && 
                           applicant.contactInfo.phones[0] &&
                           applicant.contactInfo.addresses[0].line1;
                case 3: // Family Information
                    return applicant.familyInfo.relationshipStatus;
                case 4: // Accommodation Details
                    return applicant.accommodationDetails.hasAddress !== null;
                case 5: // Employment Information
                    return applicant.employmentInfo.employmentStatus;
                case 6: // Income & Expenditure
                    return applicant.incomeExpenditure.planningToExpense;
                case 7: // Travel Information
                    return applicant.travelInfo.visitMainReason &&
                           applicant.travelInfo.arrivalDate;
                case 8: // Travel History
                    return true;
                default:
                    return false;
            }
        }

        // Jump to a specific step
        function jumpToStep(stepIndex) {
            state.currentStep = stepIndex;
            generateFormSteps();
            generateStepNavigation();
            updateUI();
            saveToLocalStorage();
        }

        // Count completed steps for an applicant
        function countCompletedSteps(applicantIndex) {
            const applicant = state.applicants[applicantIndex];
            let count = 0;
            
            for (let i = 0; i < state.totalSteps; i++) {
                if (isStepCompletedForApplicant(applicantIndex, i)) {
                    count++;
                }
            }
            
            return count;
        }

        // Check if a step is completed for a specific applicant
        function isStepCompletedForApplicant(applicantIndex, stepIndex) {
            const applicant = state.applicants[applicantIndex];
            
            switch(stepIndex) {
                case 0: // Passport Information
                    return applicant.passportInfo.pp_given_name && 
                           applicant.passportInfo.pp_family_name &&
                           applicant.passportInfo.pp_number;
                case 1: // NID Information
                    return applicant.nidInfo.has_nid !== null;
                case 2: // Contact Information
                    return applicant.contactInfo.emails[0] && 
                           applicant.contactInfo.phones[0] &&
                           applicant.contactInfo.addresses[0].line1;
                case 3: // Family Information
                    return applicant.familyInfo.relationshipStatus;
                case 4: // Accommodation Details
                    return applicant.accommodationDetails.hasAddress !== null;
                case 5: // Employment Information
                    return applicant.employmentInfo.employmentStatus;
                case 6: // Income & Expenditure
                    return applicant.incomeExpenditure.planningToExpense;
                case 7: // Travel Information
                    return applicant.travelInfo.visitMainReason &&
                           applicant.travelInfo.arrivalDate;
                case 8: // Travel History
                    return true;
                default:
                    return false;
            }
        }

        // Check if an applicant has completed all steps
        function isApplicantComplete(applicantIndex) {
            for (let i = 0; i < state.totalSteps; i++) {
                if (!isStepCompletedForApplicant(applicantIndex, i)) {
                    return false;
                }
            }
            return true;
        }

        // Switch between applicants
        function switchApplicant(applicantIndex) {
            state.currentApplicant = applicantIndex;
            state.currentStep = 0;
            
            // Update tabs
            document.querySelectorAll('.tab').forEach((tab, index) => {
                if (index === applicantIndex) {
                    tab.classList.add('active', 'bg-blue-600', 'text-white');
                    tab.classList.remove('text-gray-500');
                } else {
                    tab.classList.remove('active', 'bg-blue-600', 'text-white');
                    tab.classList.add('text-gray-500');
                }
            });
            
            // Regenerate form steps for the selected applicant
            generateFormSteps();
            
            // Regenerate step navigation
            generateStepNavigation();
            
            // Update UI
            updateUI();
            
            // Save state
            saveToLocalStorage();
        }

        // Generate form steps for the current applicant
        function generateFormSteps() {
            const formStepsContainer = document.getElementById('form-steps');
            formStepsContainer.innerHTML = '';
            
            state.steps.forEach((step, index) => {
                const stepElement = document.createElement('div');
                stepElement.className = `step fade-in ${index === state.currentStep ? 'active' : ''}`;
                stepElement.id = `step-${index}`;
                
                stepElement.innerHTML = `
                    <h2 class="text-xl font-bold text-gray-800 mb-6">${step.name} - Applicant ${state.currentApplicant + 1}</h2>
                    <div class="bg-gray-50 p-6 rounded-lg form-section">
                        ${generateStepContent(index)}
                    </div>
                `;
                
                formStepsContainer.appendChild(stepElement);
            });
            
            // Update the total steps display
            document.getElementById('total-steps').textContent = state.totalSteps;
        }

        // Generate content for each step
        function generateStepContent(stepIndex) {
            const applicant = state.applicants[state.currentApplicant];
            
            switch(stepIndex) {
                case 0: // Passport Information
                    return generatePassportInfoStep(applicant);
                    
                case 1: // NID Information
                    return generateNIDInfoStep(applicant);
                    
                case 2: // Contact Information
                    return generateContactInfoStep(applicant);
                    
                case 3: // Family Information
                    return generateFamilyInfoStep(applicant);
                    
                case 4: // Accommodation Details
                    return generateAccommodationDetailsStep(applicant);
                    
                case 5: // Employment Information
                    return generateEmploymentInfoStep(applicant);
                    
                case 6: // Income & Expenditure
                    return generateIncomeExpenditureStep(applicant);
                    
                case 7: // Travel Information
                    return generateTravelInfoStep(applicant);
                    
                case 8: // Travel History
                    return generateTravelHistoryStep(applicant);
                    
                default:
                    return '<p>Step content not defined.</p>';
            }
        }

        // Generate Passport Information step
        function generatePassportInfoStep(applicant) {
            return `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-gray-700 mb-2">Given Name *</label>
                        <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                               value="${applicant.passportInfo.pp_given_name || ''}" 
                               onchange="updateApplicantData('passportInfo', 'pp_given_name', this.value)" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Family Name *</label>
                        <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                               value="${applicant.passportInfo.pp_family_name || ''}" 
                               onchange="updateApplicantData('passportInfo', 'pp_family_name', this.value)" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Gender *</label>
                        <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                onchange="updateApplicantData('passportInfo', 'pp_gender', this.value)" required>
                            <option value="">Select</option>
                            <option value="male" ${applicant.passportInfo.pp_gender === 'male' ? 'selected' : ''}>Male</option>
                            <option value="female" ${applicant.passportInfo.pp_gender === 'female' ? 'selected' : ''}>Female</option>
                            <option value="other" ${applicant.passportInfo.pp_gender === 'other' ? 'selected' : ''}>Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Place of Birth *</label>
                        <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                               value="${applicant.passportInfo.pp_pob || ''}" 
                               onchange="updateApplicantData('passportInfo', 'pp_pob', this.value)" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Date of Birth *</label>
                        <input type="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                               value="${applicant.passportInfo.pp_dob || ''}" 
                               onchange="updateApplicantData('passportInfo', 'pp_dob', this.value)" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Passport Number *</label>
                        <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                               value="${applicant.passportInfo.pp_number || ''}" 
                               onchange="updateApplicantData('passportInfo', 'pp_number', this.value)" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Issuing Authority *</label>
                        <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                               value="${applicant.passportInfo.pp_issuing_authority || ''}" 
                               onchange="updateApplicantData('passportInfo', 'pp_issuing_authority', this.value)" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Issue Date *</label>
                        <input type="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                               value="${applicant.passportInfo.pp_issue_date || ''}" 
                               onchange="updateApplicantData('passportInfo', 'pp_issue_date', this.value)" required>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Expiry Date *</label>
                        <input type="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                               value="${applicant.passportInfo.pp_expiry_date || ''}" 
                               onchange="updateApplicantData('passportInfo', 'pp_expiry_date', this.value)" required>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-4">* Required fields</p>
            `;
        }

        // Generate NID Information step
        function generateNIDInfoStep(applicant) {
            return `
                <div class="space-y-6">
                    <div>
                        <label class="block text-gray-700 mb-2">Do you have a valid national identity card? *</label>
                        <div class="flex space-x-4">
                            <label class="inline-flex items-center">
                                <input type="radio" name="has_nid" value="yes" class="h-4 w-4 text-blue-600" 
                                       ${applicant.nidInfo.has_nid === true ? 'checked' : ''}
                                       onchange="updateApplicantData('nidInfo', 'has_nid', this.value === 'yes')" required>
                                <span class="ml-2">Yes</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="has_nid" value="no" class="h-4 w-4 text-blue-600"
                                       ${applicant.nidInfo.has_nid === false ? 'checked' : ''}
                                       onchange="updateApplicantData('nidInfo', 'has_nid', this.value === 'yes')" required>
                                <span class="ml-2">No</span>
                            </label>
                        </div>
                    </div>
                    
                    <div id="nid-details" class="${applicant.nidInfo.has_nid ? 'block' : 'hidden'}">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-gray-700 mb-2">NID Number *</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${applicant.nidInfo.nid_number || ''}" 
                                       onchange="updateApplicantData('nidInfo', 'nid_number', this.value)" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Issuing Authority *</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${applicant.nidInfo.nid_issuing_authority || ''}" 
                                       onchange="updateApplicantData('nidInfo', 'nid_issuing_authority', this.value)" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Issue Date (If applicable)</label>
                                <input type="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${applicant.nidInfo.nid_isue_date || ''}" 
                                       onchange="updateApplicantData('nidInfo', 'nid_isue_date', this.value)">
                            </div>
                        </div>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-4">* Required fields</p>
            `;
        }

        // Generate Contact Information step with multiple fields
        function generateContactInfoStep(applicant) {
            let emailsHTML = '';
            applicant.contactInfo.emails.forEach((email, index) => {
                emailsHTML += `
                    <div class="dynamic-field-group flex items-end">
                        <div class="flex-1">
                            <label class="block text-gray-700 mb-2">Email Address ${index > 0 ? index + 1 : ''}</label>
                            <input type="email" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                   value="${email}" 
                                   onchange="updateContactArrayData('emails', ${index}, this.value)" ${index === 0 ? 'required' : ''}>
                        </div>
                        ${index > 0 ? `
                            <button type="button" class="ml-2 bg-red-500 hover:bg-red-600 text-white p-2 rounded-lg" onclick="removeContactField('emails', ${index})">
                                <i class="fas fa-times"></i>
                            </button>
                        ` : ''}
                    </div>
                `;
            });
            
            let phonesHTML = '';
            applicant.contactInfo.phones.forEach((phone, index) => {
                phonesHTML += `
                    <div class="dynamic-field-group flex items-end">
                        <div class="flex-1">
                            <label class="block text-gray-700 mb-2">Phone Number ${index > 0 ? index + 1 : ''}</label>
                            <input type="tel" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                   value="${phone}" 
                                   onchange="updateContactArrayData('phones', ${index}, this.value)" ${index === 0 ? 'required' : ''}>
                        </div>
                        ${index > 0 ? `
                            <button type="button" class="ml-2 bg-red-500 hover:bg-red-600 text-white p-2 rounded-lg" onclick="removeContactField('phones', ${index})">
                                <i class="fas fa-times"></i>
                            </button>
                        ` : ''}
                    </div>
                `;
            });
            
            let addressesHTML = '';
            applicant.contactInfo.addresses.forEach((address, index) => {
                addressesHTML += `
                    <div class="address-group">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="font-medium text-gray-700">Address ${index + 1}</h4>
                            ${index > 0 ? `
                                <button type="button" class="bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-lg text-sm" onclick="removeContactField('addresses', ${index})">
                                    Remove Address
                                </button>
                            ` : ''}
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-gray-700 mb-2">Line 1 *</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${address.line1 || ''}" 
                                       onchange="updateContactAddressData(${index}, 'line1', this.value)" required>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-gray-700 mb-2">Line 2</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${address.line2 || ''}" 
                                       onchange="updateContactAddressData(${index}, 'line2', this.value)">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">City *</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${address.city || ''}" 
                                       onchange="updateContactAddressData(${index}, 'city', this.value)" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">State *</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${address.state || ''}" 
                                       onchange="updateContactAddressData(${index}, 'state', this.value)" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Postal Code *</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${address.postalCode || ''}" 
                                       onchange="updateContactAddressData(${index}, 'postalCode', this.value)" required>
                            </div>
                            <div class="md:col-span-2">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" class="h-4 w-4 text-blue-600" 
                                           ${address.isCorrespondence ? 'checked' : ''}
                                           onchange="updateContactAddressData(${index}, 'isCorrespondence', this.checked)">
                                    <span class="ml-2">Is this address also your correspondence address?</span>
                                </label>
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">How long have you lived at this address?</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${address.livedInFor || ''}" 
                                       onchange="updateContactAddressData(${index}, 'livedInFor', this.value)">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">What is the ownership status of your home?</label>
                                <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        onchange="updateContactAddressData(${index}, 'ownershipStatus', this.value)">
                                    <option value="">Select</option>
                                    <option value="owned" ${address.ownershipStatus === 'owned' ? 'selected' : ''}>Owned</option>
                                    <option value="rented" ${address.ownershipStatus === 'rented' ? 'selected' : ''}>Rented</option>
                                    <option value="leased" ${address.ownershipStatus === 'leased' ? 'selected' : ''}>Leased</option>
                                    <option value="other" ${address.ownershipStatus === 'other' ? 'selected' : ''}>Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            // Generate options for preferred phone
            let phoneOptionsHTML = '';
            applicant.contactInfo.phones.forEach((phone, index) => {
                if (phone) {
                    phoneOptionsHTML += `<option value="${phone}" ${applicant.contactInfo.preferred_phone_no === phone ? 'selected' : ''}>${phone}</option>`;
                }
            });
            
            return `
                <div class="space-y-6">
                    <div>
                        <h3 class="text-lg font-medium text-gray-800 mb-4">Email Addresses</h3>
                        ${emailsHTML}
                        <button type="button" class="mt-2 bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-lg flex items-center" onclick="addContactField('emails')">
                            <i class="fas fa-plus mr-2"></i> Add Another Email
                        </button>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-medium text-gray-800 mb-4">Phone Numbers</h3>
                        ${phonesHTML}
                        <button type="button" class="mt-2 bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-lg flex items-center" onclick="addContactField('phones')">
                            <i class="fas fa-plus mr-2"></i> Add Another Phone
                        </button>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-medium text-gray-800 mb-4">Addresses</h3>
                        ${addressesHTML}
                        <button type="button" class="mt-2 bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-lg flex items-center" onclick="addContactField('addresses')">
                            <i class="fas fa-plus mr-2"></i> Add Another Address
                        </button>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">What is your preferred contact number?</label>
                        <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                onchange="updateApplicantData('contactInfo', 'preferred_phone_no', this.value)">
                            <option value="">Select</option>
                            ${phoneOptionsHTML}
                        </select>
                    </div>
                </div>
            `;
        }

        // Generate Family Information step
        function generateFamilyInfoStep(applicant) {
            let familyMembersHTML = '';
            applicant.familyInfo.familyMembers.forEach((member, index) => {
                familyMembersHTML += `
                    <div class="family-member-group">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="font-medium text-gray-700">Family Member ${index + 1}</h4>
                            <button type="button" class="bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-lg text-sm" onclick="removeFamilyMember(${index})">
                                Remove Member
                            </button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2">Relation *</label>
                                <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        onchange="updateFamilyMemberData(${index}, 'relation', this.value)" required>
                                    <option value="">Select</option>
                                    <option value="father" ${member.relation === 'father' ? 'selected' : ''}>Father</option>
                                    <option value="mother" ${member.relation === 'mother' ? 'selected' : ''}>Mother</option>
                                    <option value="wife" ${member.relation === 'wife' ? 'selected' : ''}>Wife</option>
                                    <option value="husband" ${member.relation === 'husband' ? 'selected' : ''}>Husband</option>
                                    <option value="son" ${member.relation === 'son' ? 'selected' : ''}>Son</option>
                                    <option value="daughter" ${member.relation === 'daughter' ? 'selected' : ''}>Daughter</option>
                                    <option value="brother" ${member.relation === 'brother' ? 'selected' : ''}>Brother</option>
                                    <option value="sister" ${member.relation === 'sister' ? 'selected' : ''}>Sister</option>
                                    <option value="other" ${member.relation === 'other' ? 'selected' : ''}>Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Given Name *</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${member.givenName || ''}" 
                                       onchange="updateFamilyMemberData(${index}, 'givenName', this.value)" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Family Name *</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${member.familyName || ''}" 
                                       onchange="updateFamilyMemberData(${index}, 'familyName', this.value)" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Date of Birth</label>
                                <input type="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${member.dob || ''}" 
                                       onchange="updateFamilyMemberData(${index}, 'dob', this.value)">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Country of Nationality</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${member.nationality || ''}" 
                                       onchange="updateFamilyMemberData(${index}, 'nationality', this.value)">
                            </div>
                            <div class="md:col-span-2">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" class="h-4 w-4 text-blue-600" 
                                           ${member.liveWith ? 'checked' : ''}
                                           onchange="updateFamilyMemberData(${index}, 'liveWith', this.checked)">
                                    <span class="ml-2">Do they currently live with you?</span>
                                </label>
                            </div>
                            <div class="md:col-span-2">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" class="h-4 w-4 text-blue-600" 
                                           ${member.travellingUK ? 'checked' : ''}
                                           onchange="updateFamilyMemberData(${index}, 'travellingUK', this.checked)">
                                    <span class="ml-2">Will they be travelling with you to the UK?</span>
                                </label>
                            </div>
                            <div id="passport-section-${index}" class="md:col-span-2 ${member.travellingUK ? 'block' : 'hidden'}">
                                <label class="block text-gray-700 mb-2">Their Passport Number *</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${member.passportNo || ''}" 
                                       onchange="updateFamilyMemberData(${index}, 'passportNo', this.value)" ${member.travellingUK ? 'required' : ''}>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            return `
                <div class="space-y-6">
                    <div>
                        <label class="block text-gray-700 mb-2">What is your relationship status? *</label>
                        <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                onchange="updateApplicantData('familyInfo', 'relationshipStatus', this.value)" required>
                            <option value="">Select</option>
                            <option value="single" ${applicant.familyInfo.relationshipStatus === 'single' ? 'selected' : ''}>Single</option>
                            <option value="married" ${applicant.familyInfo.relationshipStatus === 'married' ? 'selected' : ''}>Married</option>
                            <option value="divorced" ${applicant.familyInfo.relationshipStatus === 'divorced' ? 'selected' : ''}>Divorced</option>
                            <option value="widowed" ${applicant.familyInfo.relationshipStatus === 'widowed' ? 'selected' : ''}>Widowed</option>
                            <option value="separated" ${applicant.familyInfo.relationshipStatus === 'separated' ? 'selected' : ''}>Separated</option>
                        </select>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-medium text-gray-800 mb-4">Family Members</h3>
                        ${familyMembersHTML || '<p class="text-gray-500">No family members added yet.</p>'}
                        <button type="button" class="mt-2 bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-lg flex items-center" onclick="addFamilyMember()">
                            <i class="fas fa-plus mr-2"></i> Add Family Member
                        </button>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Do you have any family in the UK?</label>
                        <div class="flex space-x-4">
                            <label class="inline-flex items-center">
                                <input type="radio" name="has_relative_in_uk" value="yes" class="h-4 w-4 text-blue-600" 
                                       ${applicant.familyInfo.hasRelativeInUK === true ? 'checked' : ''}
                                       onchange="updateApplicantData('familyInfo', 'hasRelativeInUK', this.value === 'yes')">
                                <span class="ml-2">Yes</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="has_relative_in_uk" value="no" class="h-4 w-4 text-blue-600"
                                       ${applicant.familyInfo.hasRelativeInUK === false ? 'checked' : ''}
                                       onchange="updateApplicantData('familyInfo', 'hasRelativeInUK', this.value === 'yes')">
                                <span class="ml-2">No</span>
                            </label>
                        </div>
                    </div>
                    
                    ${applicant.familyInfo.hasRelativeInUK ? `
                        <div class="address-group">
                            <h4 class="font-medium text-gray-700 mb-4">Relative's Address in UK</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-gray-700 mb-2">Line 1</label>
                                    <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                           value="${applicant.familyInfo.relativeAddress.line1 || ''}" 
                                           onchange="updateFamilyRelativeAddress('line1', this.value)">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-gray-700 mb-2">Line 2</label>
                                    <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                           value="${applicant.familyInfo.relativeAddress.line2 || ''}" 
                                           onchange="updateFamilyRelativeAddress('line2', this.value)">
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2">City</label>
                                    <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                           value="${applicant.familyInfo.relativeAddress.city || ''}" 
                                           onchange="updateFamilyRelativeAddress('city', this.value)">
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2">State</label>
                                    <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                           value="${applicant.familyInfo.relativeAddress.state || ''}" 
                                           onchange="updateFamilyRelativeAddress('state', this.value)">
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2">Postal Code</label>
                                    <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                           value="${applicant.familyInfo.relativeAddress.postalCode || ''}" 
                                           onchange="updateFamilyRelativeAddress('postalCode', this.value)">
                                </div>
                            </div>
                        </div>
                    ` : ''}
                </div>
            `;
        }

        // Generate Accommodation Details step
        function generateAccommodationDetailsStep(applicant) {
            let addressesHTML = '';
            applicant.accommodationDetails.addresses.forEach((address, index) => {
                addressesHTML += `
                    <div class="address-group">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="font-medium text-gray-700">Accommodation Address ${index + 1}</h4>
                            ${index > 0 ? `
                                <button type="button" class="bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-lg text-sm" onclick="removeAccommodationField('addresses', ${index})">
                                    Remove Address
                                </button>
                            ` : ''}
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2">Hotel Name</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${applicant.accommodationDetails.hotels[index] || ''}" 
                                       onchange="updateAccommodationArrayData('hotels', ${index}, this.value)">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-gray-700 mb-2">Line 1</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${address.line1 || ''}" 
                                       onchange="updateAccommodationAddressData(${index}, 'line1', this.value)">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-gray-700 mb-2">Line 2</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${address.line2 || ''}" 
                                       onchange="updateAccommodationAddressData(${index}, 'line2', this.value)">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">City</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${address.city || ''}" 
                                       onchange="updateAccommodationAddressData(${index}, 'city', this.value)">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">State</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${address.state || ''}" 
                                       onchange="updateAccommodationAddressData(${index}, 'state', this.value)">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Postal Code</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${address.postalCode || ''}" 
                                       onchange="updateAccommodationAddressData(${index}, 'postalCode', this.value)">
                            </div>
                        </div>
                    </div>
                `;
            });
            
            return `
                <div class="space-y-6">
                    <div>
                        <label class="block text-gray-700 mb-2">Do you have an address for where you are going to stay in the UK?</label>
                        <div class="flex space-x-4">
                            <label class="inline-flex items-center">
                                <input type="radio" name="ad_have_address" value="yes" class="h-4 w-4 text-blue-600" 
                                       ${applicant.accommodationDetails.hasAddress === true ? 'checked' : ''}
                                       onchange="updateApplicantData('accommodationDetails', 'hasAddress', this.value === 'yes')">
                                <span class="ml-2">Yes</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="ad_have_address" value="no" class="h-4 w-4 text-blue-600"
                                       ${applicant.accommodationDetails.hasAddress === false ? 'checked' : ''}
                                       onchange="updateApplicantData('accommodationDetails', 'hasAddress', this.value === 'yes')">
                                <span class="ml-2">No</span>
                            </label>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-medium text-gray-800 mb-4">Accommodation Addresses</h3>
                        ${addressesHTML}
                        <button type="button" class="mt-2 bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-lg flex items-center" onclick="addAccommodationField('addresses')">
                            <i class="fas fa-plus mr-2"></i> Add Another Address
                        </button>
                    </div>
                </div>
            `;
        }

        // Generate Employment Information step
        function generateEmploymentInfoStep(applicant) {
            return `
                <div class="space-y-6">
                    <div>
                        <label class="block text-gray-700 mb-2">What is your employment status? *</label>
                        <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                onchange="updateApplicantData('employmentInfo', 'employmentStatus', this.value)" required>
                            <option value="">Select</option>
                            <option value="employed" ${applicant.employmentInfo.employmentStatus === 'employed' ? 'selected' : ''}>Employed</option>
                            <option value="self-employed" ${applicant.employmentInfo.employmentStatus === 'self-employed' ? 'selected' : ''}>Self-Employed</option>
                            <option value="student" ${applicant.employmentInfo.employmentStatus === 'student' ? 'selected' : ''}>Student</option>
                            <option value="unemployed" ${applicant.employmentInfo.employmentStatus === 'unemployed' ? 'selected' : ''}>Unemployed</option>
                            <option value="retired" ${applicant.employmentInfo.employmentStatus === 'retired' ? 'selected' : ''}>Retired</option>
                        </select>
                    </div>
                    
                    <div id="employment-details">
                        ${applicant.employmentInfo.employmentStatus === 'self-employed' ? `
                            <div>
                                <label class="block text-gray-700 mb-2">What is your job? *</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${applicant.employmentInfo.jobDetails || ''}" 
                                       onchange="updateApplicantData('employmentInfo', 'jobDetails', this.value)" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">How much do you earn from this job in a year? *</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${applicant.employmentInfo.yearlyEarning || ''}" 
                                       onchange="updateApplicantData('employmentInfo', 'yearlyEarning', this.value)" required>
                            </div>
                        ` : ''}
                        
                        ${applicant.employmentInfo.employmentStatus === 'employed' ? `
                            <div>
                                <label class="block text-gray-700 mb-2">Job Title *</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${applicant.employmentInfo.jobTitle || ''}" 
                                       onchange="updateApplicantData('employmentInfo', 'jobTitle', this.value)" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">How much do you earn each month - after tax? *</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${applicant.employmentInfo.monthlyIncome || ''}" 
                                       onchange="updateApplicantData('employmentInfo', 'monthlyIncome', this.value)" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Job Description</label>
                                <textarea class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" rows="3"
                                          onchange="updateApplicantData('employmentInfo', 'jobDescription', this.value)">${applicant.employmentInfo.jobDescription || ''}</textarea>
                            </div>
                        ` : ''}
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-4">* Required fields</p>
            `;
        }

        // Generate Income & Expenditure step
        function generateIncomeExpenditureStep(applicant) {
            let paymentInfoHTML = '';
            applicant.incomeExpenditure.paymentInfo.forEach((payment, index) => {
                paymentInfoHTML += `
                    <div class="dynamic-field-group">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="font-medium text-gray-700">Payment Source ${index + 1}</h4>
                            ${index > 0 ? `
                                <button type="button" class="bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-lg text-sm" onclick="removePaymentInfo(${index})">
                                    Remove Payment
                                </button>
                            ` : ''}
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2">Currency</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${payment.currency || ''}" 
                                       onchange="updatePaymentInfoData(${index}, 'currency', this.value)">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Amount</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${payment.amount || ''}" 
                                       onchange="updatePaymentInfoData(${index}, 'amount', this.value)">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-gray-700 mb-2">What are you being paid for</label>
                                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       value="${payment.paidFor || ''}" 
                                       onchange="updatePaymentInfoData(${index}, 'paidFor', this.value)">
                            </div>
                        </div>
                    </div>
                `;
            });
            
            return `
                <div class="space-y-6">
                    <div>
                        <label class="block text-gray-700 mb-2">Do you have another or any savings?</label>
                        <div class="flex space-x-4">
                            <label class="inline-flex items-center">
                                <input type="radio" name="have_savings" value="yes" class="h-4 w-4 text-blue-600" 
                                       ${applicant.incomeExpenditure.haveSavings === true ? 'checked' : ''}
                                       onchange="updateApplicantData('incomeExpenditure', 'haveSavings', this.value === 'yes')">
                                <span class="ml-2">Yes</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="have_savings" value="no" class="h-4 w-4 text-blue-600"
                                       ${applicant.incomeExpenditure.haveSavings === false ? 'checked' : ''}
                                       onchange="updateApplicantData('incomeExpenditure', 'haveSavings', this.value === 'yes')">
                                <span class="ml-2">No</span>
                            </label>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">How much money are you personally planning to spend on your visit to the UK? *</label>
                        <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                               value="${applicant.incomeExpenditure.planningToExpense || ''}" 
                               onchange="updateApplicantData('incomeExpenditure', 'planningToExpense', this.value)" required>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">What is the total amount of money you spend each month?</label>
                        <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                               value="${applicant.incomeExpenditure.totalExpenseInBd || ''}" 
                               onchange="updateApplicantData('incomeExpenditure', 'totalExpenseInBd', this.value)">
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-medium text-gray-800 mb-4">Payment Information</h3>
                        ${paymentInfoHTML}
                        <button type="button" class="mt-2 bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-lg flex items-center" onclick="addPaymentInfo()">
                            <i class="fas fa-plus mr-2"></i> Add Payment Source
                        </button>
                    </div>
                </div>
            `;
        }

        // Generate Travel Information step
        function generateTravelInfoStep(applicant) {
            return `
                <div class="space-y-6">
                    <div>
                        <label class="block text-gray-700 mb-2">What is the main reason for your visit to the UK? *</label>
                        <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                onchange="updateApplicantData('travelInfo', 'visitMainReason', this.value)" required>
                            <option value="">Select</option>
                            <option value="tourism" ${applicant.travelInfo.visitMainReason === 'tourism' ? 'selected' : ''}>Tourism</option>
                            <option value="business" ${applicant.travelInfo.visitMainReason === 'business' ? 'selected' : ''}>Business</option>
                            <option value="study" ${applicant.travelInfo.visitMainReason === 'study' ? 'selected' : ''}>Study</option>
                            <option value="family" ${applicant.travelInfo.visitMainReason === 'family' ? 'selected' : ''}>Family Visit</option>
                            <option value="medical" ${applicant.travelInfo.visitMainReason === 'medical' ? 'selected' : ''}>Medical Treatment</option>
                        </select>
                    </div>
                    
                    <div id="travel-reason-details">
                        ${applicant.travelInfo.visitMainReason === 'business' ? `
                            <div>
                                <label class="block text-gray-700 mb-2">What is the main reason for your business visit to the UK?</label>
                                <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        onchange="updateApplicantData('travelInfo', 'businessReasonToVisitUk', this.value)">
                                    <option value="">Select</option>
                                    <option value="meetings" ${applicant.travelInfo.businessReasonToVisitUk === 'meetings' ? 'selected' : ''}>Attend business meetings</option>
                                    <option value="training" ${applicant.travelInfo.businessReasonToVisitUk === 'training' ? 'selected' : ''}>Business-related training</option>
                                    <option value="conference" ${applicant.travelInfo.businessReasonToVisitUk === 'conference' ? 'selected' : ''}>Attend conference</option>
                                    <option value="negotiations" ${applicant.travelInfo.businessReasonToVisitUk === 'negotiations' ? 'selected' : ''}>Business negotiations</option>
                                </select>
                            </div>
                        ` : ''}
                        
                        ${applicant.travelInfo.visitMainReason === 'tourism' ? `
                            <div>
                                <label class="block text-gray-700 mb-2">What is the main reason for your holiday visit to the UK?</label>
                                <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        onchange="updateApplicantData('travelInfo', 'tourismReasonToVisitUk', this.value)">
                                    <option value="">Select</option>
                                    <option value="tourist" ${applicant.travelInfo.tourismReasonToVisitUk === 'tourist' ? 'selected' : ''}>Tourist</option>
                                    <option value="family" ${applicant.travelInfo.tourismReasonToVisitUk === 'family' ? 'selected' : ''}>Visiting family</option>
                                    <option value="friends" ${applicant.travelInfo.tourismReasonToVisitUk === 'friends' ? 'selected' : ''}>Visiting friends</option>
                                </select>
                            </div>
                        ` : ''}
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Give details of the main purpose of your visit and anything else you plan to do on your trip.</label>
                        <textarea class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" rows="3"
                                  maxlength="500"
                                  onchange="updateApplicantData('travelInfo', 'activities', this.value)">${applicant.travelInfo.activities || ''}</textarea>
                        <p class="text-sm text-gray-500 mt-1">Maximum 500 characters</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-gray-700 mb-2">Date you plan to arrive in the UK *</label>
                            <input type="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                   value="${applicant.travelInfo.arrivalDate || ''}" 
                                   onchange="updateApplicantData('travelInfo', 'arrivalDate', this.value)" required>
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Date you plan to leave the UK *</label>
                            <input type="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                   value="${applicant.travelInfo.leaveDate || ''}" 
                                   onchange="updateApplicantData('travelInfo', 'leaveDate', this.value)" required>
                        </div>
                    </div>
                </div>
            `;
        }

        // Generate Travel History step
        function generateTravelHistoryStep(applicant) {
            return `
                <div class="space-y-6">
                    <div>
                        <label class="block text-gray-700 mb-2">Travel History</label>
                        <textarea class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" rows="6"
                                  placeholder="Please provide details of your travel history including countries visited, dates, and purpose of visit"
                                  onchange="updateApplicantData('travelHistory', 'history', this.value)">${applicant.travelHistory.history || ''}</textarea>
                        <p class="text-sm text-gray-500 mt-1">Provide details of countries visited, dates, and purpose of visit</p>
                    </div>
                </div>
            `;
        }

        // Add a new contact field (email, phone, or address)
        function addContactField(type) {
            const applicant = state.applicants[state.currentApplicant];
            
            if (type === 'emails') {
                applicant.contactInfo.emails.push('');
            } else if (type === 'phones') {
                applicant.contactInfo.phones.push('');
            } else if (type === 'addresses') {
                applicant.contactInfo.addresses.push({
                    line1: '',
                    line2: '',
                    city: '',
                    state: '',
                    postalCode: '',
                    isCorrespondence: false,
                    livedInFor: '',
                    ownershipStatus: ''
                });
            }
            
            generateFormSteps();
            saveToLocalStorage();
        }

        // Remove a contact field
        function removeContactField(type, index) {
            const applicant = state.applicants[state.currentApplicant];
            
            if (type === 'emails') {
                applicant.contactInfo.emails.splice(index, 1);
            } else if (type === 'phones') {
                applicant.contactInfo.phones.splice(index, 1);
            } else if (type === 'addresses') {
                applicant.contactInfo.addresses.splice(index, 1);
            }
            
            generateFormSteps();
            saveToLocalStorage();
        }

        // Update contact array data (emails or phones)
        function updateContactArrayData(field, index, value) {
            const applicant = state.applicants[state.currentApplicant];
            applicant.contactInfo[field][index] = value;
            saveToLocalStorage();
        }

        // Update contact address data
        function updateContactAddressData(addressIndex, field, value) {
            const applicant = state.applicants[state.currentApplicant];
            applicant.contactInfo.addresses[addressIndex][field] = value;
            saveToLocalStorage();
        }

        // Add a new family member
        function addFamilyMember() {
            const applicant = state.applicants[state.currentApplicant];
            applicant.familyInfo.familyMembers.push({
                relation: '',
                givenName: '',
                familyName: '',
                dob: '',
                nationality: '',
                liveWith: false,
                travellingUK: false,
                passportNo: ''
            });
            
            generateFormSteps();
            saveToLocalStorage();
        }

        // Remove a family member
        function removeFamilyMember(index) {
            const applicant = state.applicants[state.currentApplicant];
            applicant.familyInfo.familyMembers.splice(index, 1);
            
            generateFormSteps();
            saveToLocalStorage();
        }

        // Update family member data
        function updateFamilyMemberData(memberIndex, field, value) {
            const applicant = state.applicants[state.currentApplicant];
            applicant.familyInfo.familyMembers[memberIndex][field] = value;
            
            // Show/hide passport section based on travellingUK
            if (field === 'travellingUK') {
                const passportSection = document.getElementById(`passport-section-${memberIndex}`);
                if (passportSection) {
                    if (value) {
                        passportSection.classList.remove('hidden');
                        passportSection.classList.add('block');
                    } else {
                        passportSection.classList.remove('block');
                        passportSection.classList.add('hidden');
                    }
                }
            }
            
            saveToLocalStorage();
        }

        // Update family relative address
        function updateFamilyRelativeAddress(field, value) {
            const applicant = state.applicants[state.currentApplicant];
            applicant.familyInfo.relativeAddress[field] = value;
            saveToLocalStorage();
        }

        // Add a new accommodation field (hotels or addresses)
        function addAccommodationField(type) {
            const applicant = state.applicants[state.currentApplicant];
            
            if (type === 'hotels') {
                applicant.accommodationDetails.hotels.push('');
            } else if (type === 'addresses') {
                applicant.accommodationDetails.addresses.push({
                    line1: '',
                    line2: '',
                    city: '',
                    state: '',
                    postalCode: ''
                });
            }
            
            generateFormSteps();
            saveToLocalStorage();
        }

        // Remove an accommodation field
        function removeAccommodationField(type, index) {
            const applicant = state.applicants[state.currentApplicant];
            
            if (type === 'hotels') {
                applicant.accommodationDetails.hotels.splice(index, 1);
            } else if (type === 'addresses') {
                applicant.accommodationDetails.addresses.splice(index, 1);
            }
            
            generateFormSteps();
            saveToLocalStorage();
        }

        // Update accommodation array data (hotels)
        function updateAccommodationArrayData(field, index, value) {
            const applicant = state.applicants[state.currentApplicant];
            applicant.accommodationDetails[field][index] = value;
            saveToLocalStorage();
        }

        // Update accommodation address data
        function updateAccommodationAddressData(addressIndex, field, value) {
            const applicant = state.applicants[state.currentApplicant];
            applicant.accommodationDetails.addresses[addressIndex][field] = value;
            saveToLocalStorage();
        }

        // Add a new payment info
        function addPaymentInfo() {
            const applicant = state.applicants[state.currentApplicant];
            applicant.incomeExpenditure.paymentInfo.push({
                currency: '',
                amount: '',
                paidFor: ''
            });
            
            generateFormSteps();
            saveToLocalStorage();
        }

        // Remove a payment info
        function removePaymentInfo(index) {
            const applicant = state.applicants[state.currentApplicant];
            applicant.incomeExpenditure.paymentInfo.splice(index, 1);
            
            generateFormSteps();
            saveToLocalStorage();
        }

        // Update payment info data
        function updatePaymentInfoData(paymentIndex, field, value) {
            const applicant = state.applicants[state.currentApplicant];
            applicant.incomeExpenditure.paymentInfo[paymentIndex][field] = value;
            saveToLocalStorage();
        }

        // Update applicant data when form fields change
        function updateApplicantData(category, field, value) {
            state.applicants[state.currentApplicant][category][field] = value;
            
            // Special handling for NID info
            if (category === 'nidInfo' && field === 'has_nid') {
                const nidDetails = document.getElementById('nid-details');
                if (value) {
                    nidDetails.classList.remove('hidden');
                    nidDetails.classList.add('block');
                } else {
                    nidDetails.classList.remove('block');
                    nidDetails.classList.add('hidden');
                }
            }
            
            // Special handling for employment status
            if (category === 'employmentInfo' && field === 'employmentStatus') {
                // Regenerate form to show/hide employment details
                generateFormSteps();
            }
            
            // Special handling for travel reason
            if (category === 'travelInfo' && field === 'visitMainReason') {
                // Regenerate form to show/hide travel reason details
                generateFormSteps();
            }
            
            // Check if current applicant is now complete
            if (isApplicantComplete(state.currentApplicant)) {
                state.applicants[state.currentApplicant].completed = true;
            }
            
            // Update step navigation to reflect completion status
            generateStepNavigation();
            
            // Save to localStorage
            saveToLocalStorage();
            
            // Update progress indicators
            updateProgressIndicators();
        }

        // Navigate to the next step
        function nextStep() {
            // Validate current step before proceeding
            if (!validateCurrentStep()) {
                alert('Please fill in all required fields before proceeding.');
                return;
            }
            
            if (state.currentStep < state.totalSteps - 1) {
                state.currentStep++;
                generateFormSteps();
                generateStepNavigation();
                updateUI();
            } else {
                // Mark current applicant as complete
                state.applicants[state.currentApplicant].completed = true;
                
                // Check if all applicants are complete
                const allApplicantsComplete = state.applicants.every(applicant => applicant.completed);
                
                if (allApplicantsComplete) {
                    // All applicants completed, show summary
                    showSummary();
                } else if (state.currentApplicant < state.totalApplicants - 1) {
                    // Show next applicant button
                    document.getElementById('next-applicant-btn').classList.remove('hidden');
                    document.getElementById('next-btn').classList.add('hidden');
                }
                
                // Update progress
                updateProgressIndicators();
            }
            
            // Save state
            saveToLocalStorage();
        }

        // Move to the next applicant
        function nextApplicant() {
            if (state.currentApplicant < state.totalApplicants - 1) {
                state.currentApplicant++;
                state.currentStep = 0;
                
                // Hide next applicant button
                document.getElementById('next-applicant-btn').classList.add('hidden');
                document.getElementById('next-btn').classList.remove('hidden');
                
                // Switch to the next applicant
                switchApplicant(state.currentApplicant);
            } else {
                // This is the last applicant, show summary
                showSummary();
            }
        }

        // Navigate to the previous step
        function previousStep() {
            if (state.currentStep > 0) {
                state.currentStep--;
                generateFormSteps();
                generateStepNavigation();
                updateUI();
            } else if (state.currentStep === 0 && state.currentApplicant > 0) {
                // Move to the previous applicant's last step
                state.currentApplicant--;
                state.currentStep = state.totalSteps - 1;
                switchApplicant(state.currentApplicant);
            }
            
            // Save state
            saveToLocalStorage();
        }

        // Validate current step
        function validateCurrentStep() {
            // In a real application, this would validate all required fields in the current step
            // For this demo, we'll just return true
            return true;
        }

        // Update the UI based on current state
        function updateUI() {
            // Update step display
            document.querySelectorAll('.step').forEach((step, index) => {
                if (index === state.currentStep) {
                    step.classList.add('active');
                } else {
                    step.classList.remove('active');
                }
            });
            
            // Update individual progress bar
            const individualProgressPercentage = ((state.currentStep + 1) / state.totalSteps) * 100;
            document.getElementById('individual-progress-bar').style.width = `${individualProgressPercentage}%`;
            
            // Update step counter
            document.getElementById('current-step').textContent = state.currentStep + 1;
            document.getElementById('current-applicant-number').textContent = state.currentApplicant + 1;
            
            // Update button visibility
            if (state.currentStep === 0 && state.currentApplicant === 0) {
                document.getElementById('prev-btn').classList.add('hidden');
            } else {
                document.getElementById('prev-btn').classList.remove('hidden');
            }
            
            // Check if we're on the summary step (special case)
            const isSummaryStep = document.getElementById('form-steps').innerHTML.includes('Application Summary');
            
            if (isSummaryStep) {
                document.getElementById('submit-btn').classList.add('hidden');
                document.getElementById('next-btn').classList.add('hidden');
                document.getElementById('next-applicant-btn').classList.add('hidden');
            } else if (state.currentStep === state.totalSteps - 1) {
                // Always show "Next" on the last form step to go to Summary
                document.getElementById('submit-btn').classList.add('hidden');
                document.getElementById('next-btn').classList.remove('hidden');
                document.getElementById('next-applicant-btn').classList.add('hidden');
            } else {
                document.getElementById('submit-btn').classList.add('hidden');
                document.getElementById('next-btn').classList.remove('hidden');
                document.getElementById('next-applicant-btn').classList.add('hidden');
            }
            
            // Update progress indicators
            updateProgressIndicators();
        }

        // Update progress indicators
        function updateProgressIndicators() {
            // Update overall progress
            const completedApplicants = state.applicants.filter(app => app.completed).length;
            const overallProgressPercentage = (completedApplicants / state.totalApplicants) * 100;
            document.getElementById('overall-progress-bar').style.width = `${overallProgressPercentage}%`;
            document.getElementById('completed-applicants').textContent = completedApplicants;
            
            // Update tabs with progress
            generateTabs();
        }

        // Show the summary of all applicants
        function showSummary() {
            const formStepsContainer = document.getElementById('form-steps');
            formStepsContainer.innerHTML = '';
            
            const summaryElement = document.createElement('div');
            summaryElement.className = 'step active fade-in';
            summaryElement.innerHTML = `
                <h2 class="text-xl font-bold text-gray-800 mb-6">Application Summary</h2>
                <div class="bg-gray-50 p-6 rounded-lg">
                    <div class="mb-6 p-4 bg-green-50 rounded-lg border border-green-200">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                            <div>
                                <h3 class="font-medium text-green-800">All Applicants Completed</h3>
                                <p class="text-green-700 text-sm mt-1">All ${state.totalApplicants} applicants have completed their forms. Review the information below before submission.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div id="summary-content">
                        ${generateSummaryContent()}
                    </div>
                    
                    <div class="mt-8 flex justify-between">
                        <button id="download-json" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition duration-300 flex items-center">
                            <i class="fas fa-download mr-2"></i> Download JSON
                        </button>
                        <button id="submit-final" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-6 rounded-lg transition duration-300">
                            Submit Application
                        </button>
                    </div>
                    
                    <div class="mt-8 p-4 bg-blue-50 rounded-lg">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-blue-500 mt-1"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800">Important Information</h3>
                                <div class="mt-2 text-sm text-blue-700">
                                    <p>By submitting this application, you confirm that all information provided is true and accurate to the best of your knowledge.</p>
                                    <p class="mt-2">Providing false information may result in your application being refused and could affect future visa applications.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            formStepsContainer.appendChild(summaryElement);
            
            // Add event listeners for summary buttons
            document.getElementById('download-json').addEventListener('click', downloadJSON);
            document.getElementById('submit-final').addEventListener('click', submitApplication);
            
            // Update buttons for summary view
            document.getElementById('prev-btn').classList.remove('hidden');
            document.getElementById('next-btn').classList.add('hidden');
            document.getElementById('next-applicant-btn').classList.add('hidden');
            document.getElementById('submit-btn').classList.add('hidden');
            
            // Update progress bars to show completion
            document.getElementById('individual-progress-bar').style.width = '100%';
            document.getElementById('overall-progress-bar').style.width = '100%';
            document.getElementById('current-step').textContent = 'Summary';
        }

        // Generate summary content for all applicants
        function generateSummaryContent() {
            let summaryHTML = '';
            
            for (let i = 0; i < state.totalApplicants; i++) {
                const applicant = state.applicants[i];
                
                summaryHTML += `
                    <div class="mb-8 border border-gray-200 rounded-lg overflow-hidden">
                        <div class="bg-gray-100 px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                            <h3 class="font-bold text-gray-800">Applicant ${i + 1}</h3>
                            <span class="text-xs font-mono bg-blue-100 text-blue-800 py-1 px-2 rounded">${applicant.id}</span>
                        </div>
                        <div class="p-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Passport Information -->
                                <div class="summary-item">
                                    <h4 class="font-medium text-gray-700">Passport Information</h4>
                                    <p class="text-gray-600">Given Name: ${applicant.passportInfo.pp_given_name || 'Not provided'}</p>
                                    <p class="text-gray-600">Family Name: ${applicant.passportInfo.pp_family_name || 'Not provided'}</p>
                                    <p class="text-gray-600">Gender: ${applicant.passportInfo.pp_gender || 'Not provided'}</p>
                                    <p class="text-gray-600">Passport Number: ${applicant.passportInfo.pp_number || 'Not provided'}</p>
                                    <p class="text-gray-600">Issue Date: ${applicant.passportInfo.pp_issue_date || 'Not provided'}</p>
                                    <p class="text-gray-600">Expiry Date: ${applicant.passportInfo.pp_expiry_date || 'Not provided'}</p>
                                </div>
                                
                                <!-- NID Information -->
                                <div class="summary-item">
                                    <h4 class="font-medium text-gray-700">NID Information</h4>
                                    <p class="text-gray-600">Has NID: ${applicant.nidInfo.has_nid !== null ? (applicant.nidInfo.has_nid ? 'Yes' : 'No') : 'Not provided'}</p>
                                    ${applicant.nidInfo.has_nid ? `
                                        <p class="text-gray-600">NID Number: ${applicant.nidInfo.nid_number || 'Not provided'}</p>
                                        <p class="text-gray-600">Issuing Authority: ${applicant.nidInfo.nid_issuing_authority || 'Not provided'}</p>
                                    ` : ''}
                                </div>
                                
                                <!-- Contact Information -->
                                <div class="summary-item">
                                    <h4 class="font-medium text-gray-700">Contact Information</h4>
                                    <p class="text-gray-600">Emails: ${applicant.contactInfo.emails.filter(e => e).join(', ') || 'Not provided'}</p>
                                    <p class="text-gray-600">Phones: ${applicant.contactInfo.phones.filter(p => p).join(', ') || 'Not provided'}</p>
                                    <p class="text-gray-600">Preferred Phone: ${applicant.contactInfo.preferred_phone_no || 'Not provided'}</p>
                                </div>
                                
                                <!-- Addresses -->
                                <div class="summary-item">
                                    <h4 class="font-medium text-gray-700">Addresses</h4>
                                    ${applicant.contactInfo.addresses.map((addr, idx) => `
                                        <div class="mb-2">
                                            <p class="text-gray-600 font-medium">Address ${idx + 1}:</p>
                                            <p class="text-gray-600">${addr.line1 || ''} ${addr.line2 || ''}</p>
                                            <p class="text-gray-600">${addr.city || ''}, ${addr.state || ''} ${addr.postalCode || ''}</p>
                                        </div>
                                    `).join('')}
                                </div>
                                
                                <!-- Family Information -->
                                <div class="summary-item">
                                    <h4 class="font-medium text-gray-700">Family Information</h4>
                                    <p class="text-gray-600">Relationship Status: ${applicant.familyInfo.relationshipStatus || 'Not provided'}</p>
                                    <p class="text-gray-600">Family Members: ${applicant.familyInfo.familyMembers.length || '0'}</p>
                                    <p class="text-gray-600">Has Relative in UK: ${applicant.familyInfo.hasRelativeInUK !== null ? (applicant.familyInfo.hasRelativeInUK ? 'Yes' : 'No') : 'Not provided'}</p>
                                </div>
                                
                                <!-- Employment Information -->
                                <div class="summary-item">
                                    <h4 class="font-medium text-gray-700">Employment Information</h4>
                                    <p class="text-gray-600">Employment Status: ${applicant.employmentInfo.employmentStatus || 'Not provided'}</p>
                                    ${applicant.employmentInfo.employmentStatus === 'self-employed' ? `
                                        <p class="text-gray-600">Job: ${applicant.employmentInfo.jobDetails || 'Not provided'}</p>
                                        <p class="text-gray-600">Yearly Earning: ${applicant.employmentInfo.yearlyEarning || 'Not provided'}</p>
                                    ` : ''}
                                    ${applicant.employmentInfo.employmentStatus === 'employed' ? `
                                        <p class="text-gray-600">Job Title: ${applicant.employmentInfo.jobTitle || 'Not provided'}</p>
                                        <p class="text-gray-600">Monthly Income: ${applicant.employmentInfo.monthlyIncome || 'Not provided'}</p>
                                    ` : ''}
                                </div>
                                
                                <!-- Travel Information -->
                                <div class="summary-item">
                                    <h4 class="font-medium text-gray-700">Travel Information</h4>
                                    <p class="text-gray-600">Main Reason: ${applicant.travelInfo.visitMainReason || 'Not provided'}</p>
                                    <p class="text-gray-600">Arrival Date: ${applicant.travelInfo.arrivalDate || 'Not provided'}</p>
                                    <p class="text-gray-600">Departure Date: ${applicant.travelInfo.leaveDate || 'Not provided'}</p>
                                </div>
                                
                                <!-- Travel History -->
                                <div class="summary-item">
                                    <h4 class="font-medium text-gray-700">Travel History</h4>
                                    <p class="text-gray-600">${applicant.travelHistory.history || 'Not provided'}</p>
                                </div>
                                
                                <!-- Application Status -->
                                <div class="summary-item md:col-span-2">
                                    <h4 class="font-medium text-gray-700">Application Status</h4>
                                    <p class="text-gray-600">Completed: ${applicant.completed ? 'Yes' : 'No'}</p>
                                    <p class="text-gray-600">Steps filled: ${countCompletedSteps(i)}/${state.totalSteps}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            return summaryHTML;
        }

        // Download JSON data
        function downloadJSON() {
            const applicationData = {
                pnr: state.pnr,
                totalApplicants: state.totalApplicants,
                applicants: state.applicants,
                timestamp: new Date().toISOString()
            };
            
            const dataStr = JSON.stringify(applicationData, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            
            const link = document.createElement('a');
            link.href = URL.createObjectURL(dataBlob);
            link.download = `uk-visa-application-${state.pnr}.json`;
            link.click();
        }

        // Save application to localStorage
        function saveToLocalStorage() {
            const applicationData = {
                pnr: state.pnr,
                nameOfApplicant: state.applicants[0].passportInfo.pp_family_name,
                totalApplicants: state.totalApplicants,
                applicants: state.applicants,
                currentApplicant: state.currentApplicant,
                currentStep: state.currentStep,
                timestamp: new Date().toISOString()
            };
            
            localStorage.setItem('ukVisaApplication-'+state.pnr, JSON.stringify(applicationData));
        }

        // Save and exit the application
        function saveAndExit() {
            saveToLocalStorage();
            alert('Your application has been saved. You can return later to complete it.');
        }

        // Submit the application
        function submitApplication() {
            // Prepare data for API
            const applicationData = {
                pnr: state.pnr,
                nameOfApplicant: state.applicants[0].passportInfo.pp_family_name,
                totalApplicants: state.totalApplicants,
                applicants: state.applicants,
                status: "completed",
                timestamp: new Date().toISOString()
            };
            
            // Submit to server
            fetch('/server/submit-application.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(applicationData)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                alert(`Application with PNR ${state.pnr} submitted successfully!`);
                
                // Clear localStorage
                localStorage.removeItem('ukVisaApplication-'+state.pnr);
                
                // Reset the form
                document.getElementById('initial-screen').classList.remove('hidden');
                document.getElementById('multi-applicant-form').classList.add('hidden');
                document.getElementById('saved-application-section').classList.add('hidden');
                
                // Reset state
                state.currentApplicant = 0;
                state.currentStep = 0;
                state.totalApplicants = 1;
                state.pnr = '';
                state.applicants = [];
                initializeApplicant(0);
                
                // Reset form
                document.getElementById('applicant-count').value = '1';
                
                // Redirect to application form
                window.location.href = 'application-form.php';
            })
            .catch(error => {
                console.error('Error submitting application:', error);
                alert('There was an error submitting your application. Please try again.');
            });
        }
    </script>
</body>
</html>