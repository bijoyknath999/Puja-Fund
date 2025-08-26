<?php
// Language handling
$lang = isset($_GET['lang']) ? $_GET['lang'] : (isset($_SESSION['lang']) ? $_SESSION['lang'] : 'en');
$_SESSION['lang'] = $lang;

// Language translations
$translations = [
    'en' => [
        // Common
        'app_name' => 'Puja Fund',
        'app_subtitle' => 'Fund Management System',
        'language' => 'Language',
        'english' => 'English',
        'bangla' => 'বাংলা',
        
        // Navigation
        'dashboard' => 'Dashboard',
        'transactions' => 'Transactions',
        'transfers' => 'Transfers',
        'users' => 'Users',
        'reports' => 'Reports',
        'logout' => 'Logout',
        
        // Login Page
        'login' => 'Login',
        'email_label' => 'Email Address',
        'email_placeholder' => 'Enter your email address',
        'password_label' => 'Password',
        'password_placeholder' => 'Enter your password',
        'signin_btn' => 'Sign In',
        'signing_in' => 'Signing In...',
        'invalid_credentials' => 'Invalid credentials',
        'page_title_login' => 'Login - Puja Fund',
        
        // Dashboard
        'welcome_back' => 'Welcome back',
        'fund_overview' => 'Here\'s your fund overview for today',
        'current_balance' => 'Current Balance',
        'total_collections' => 'Total Collections',
        'total_expenses' => 'Total Expenses',
        'total_transactions' => 'Total Transactions',
        'active_members' => 'Active Members',
        'this_month' => 'this month',
        'added_today' => 'added today',
        'all_verified' => 'All verified',
        'surplus' => 'Surplus',
        'deficit' => 'Deficit',
        'collections' => 'Collections',
        'expenses' => 'Expenses',
        'recent_transactions' => 'Recent Transactions',
        'view_all' => 'View All',
        'fund_balance' => 'Fund Balance',
        'quick_actions' => 'Quick Actions',
        'add_transaction' => 'Add Transaction',
        'view_all_transactions' => 'View All Transactions',
        'manage_users' => 'Manage Users',
        'generate_report' => 'Generate Report',
        'no_transactions_yet' => 'No transactions yet',
        'start_first_transaction' => 'Start by adding your first transaction',
        'quick_add_transaction' => 'Quick Add Transaction',
        'page_title_dashboard' => 'Dashboard - Puja Fund',
        'page_title_transactions' => 'Transactions - Puja Fund',
        
        // Transactions
        'transaction_type' => 'Transaction Type',
        'collection' => 'Collection',
        'expense' => 'Expense',
        'transfer' => 'Transfer',
        'amount' => 'Amount',
        'description' => 'Description',
        'brief_description' => 'Brief description',
        'date' => 'Date',
        'category' => 'Category',
        'select_category' => 'Select category',
        'mic' => 'Mic',
        'added_by' => 'Added By',
        
        // Transfers
        'transfer_funds' => 'Transfer Funds',
        'transfer_to' => 'Transfer To',
        'transfer_from' => 'Transfer From',
        'transfer_amount' => 'Transfer Amount',
        'transfer_description' => 'Transfer Description',
        'transfer_date' => 'Transfer Date',
        'new_transfer' => 'New Transfer',
        'transfer_history' => 'Transfer History',
        'transfer_successful' => 'Transfer completed successfully',
        'transfer_failed' => 'Transfer failed',
        'select_user' => 'Select User',
        'transfer_to_user' => 'Transfer to User',
        'transfer_reason' => 'Reason for Transfer',
        'confirm_transfer' => 'Confirm Transfer',
        'page_title_transfers' => 'Transfers - Puja Fund',
        'cancel' => 'Cancel',
        'save_changes' => 'Save Changes',
        'edit_transaction' => 'Edit Transaction',
        'delete_transaction' => 'Delete Transaction',
        'confirm_delete_transaction' => 'Are you sure you want to delete this transaction?',
        'actions' => 'Actions',
        'delete' => 'Delete',
        'confirm_approve_transfer' => 'Are you sure you want to approve this transfer?',
        'confirm_reject_transfer' => 'Are you sure you want to reject this transfer?',
        'approve' => 'Approve',
        'reject' => 'Reject',
        'approve_transfers' => 'Approve Transfers',
        'approve_transfers_desc' => 'Review and approve pending transfer requests',
        'pending_transfers' => 'Pending Transfers',
        'no_pending_transfers' => 'No Pending Transfers',
        'all_transfers_processed' => 'All transfer requests have been processed.',
        'profile' => 'Profile',
        'my_profile' => 'My Profile',
        'my_transactions' => 'My Transactions',
        'view_my_transaction_history' => 'View my transaction history and balance',
        'my_balance' => 'My Balance',
        'my_collections' => 'My Collections',
        'my_expenses' => 'My Expenses',
        'my_transfers' => 'My Transfers',
        'filter_transactions' => 'Filter Transactions',
        'all' => 'All',
        'year' => 'Year',
        'reset' => 'Reset',
        'user' => 'User',
        'type' => 'Type',
        'transfers' => 'Transfers',
        'from' => 'From',
        'to' => 'To',
        'completed' => 'Completed',
        'pending' => 'Pending',
        'cancelled' => 'Cancelled',
        'created_by' => 'Created By',
        'confirm_delete' => 'Confirm Delete',
        'page_title_transactions' => 'Transactions - Puja Fund',
        
        // Users
        'add_user' => 'Add User',
        'full_name' => 'Full Name',
        'name_placeholder' => 'Enter full name',
        'email_address' => 'Email Address',
        'role' => 'Role',
        'manager' => 'Manager',
        'member' => 'Member',
        'password' => 'Password',
        'password_placeholder' => 'Enter password',
        'add_user_btn' => 'Add User',
        'edit_user' => 'Edit User',
        'update_role' => 'Update Role',
        'delete_user' => 'Delete User',
        'confirm_delete_user' => 'Are you sure you want to delete this user?',
        'page_title_users' => 'Users - Puja Fund',
        
        // Reports
        'reports' => 'Reports',
        'financial_report' => 'Financial Report',
        'date_range' => 'Period',
        'from_date' => 'From Date',
        'to_date' => 'To Date',
        'generate' => 'Generate Report',
        'today' => 'Today',
        'last_7_days' => 'Last 7 Days',
        'this_month' => 'This Month',
        'this_year' => 'This Year',
        'last_30_days' => 'Last 30 Days',
        'last_year' => 'Last Year',
        'summary' => 'Summary',
        'net_balance' => 'Net Balance',
        'transaction_details' => 'Transaction Details',
        'print_report' => 'Print Report',
        'transaction_type' => 'Type',
        'no_transactions' => 'No transactions found',
        'no_transactions_period' => 'No transactions were recorded in the selected date range.',
        'back_to_dashboard' => 'Back to Dashboard',
        'page_title_reports' => 'Reports - Puja Fund',
        
        // Edit Transaction
        'edit_transaction' => 'Edit Transaction',
        'update_transaction_details' => 'Update transaction details',
        'back_to_transactions' => 'Back to Transactions',
        'update_transaction' => 'Update Transaction',
        
        // Transaction Messages
        'transaction_added_success' => 'Transaction added successfully!',
        'error_adding_transaction' => 'Error adding transaction',
        
        // Page Titles
        'page_title_installation' => 'Installation - Puja Fund',
        'page_title_edit_transaction' => 'Edit Transaction - Puja Fund',
        
        // Filter Labels
        'view_manage_transactions' => 'View and manage fund transactions',
        'from_date' => 'From Date',
        'to_date' => 'To Date',
        'apply_filter' => 'Apply Filter',
        'clear_filter' => 'Clear Filter',
        'select_year' => 'Select Year',
        'year' => 'Year',
        
        // Common Actions
        'add' => 'Add',
        'edit' => 'Edit',
        'update' => 'Update',
        'close' => 'Close',
        'submit' => 'Submit',
        'reset' => 'Reset',
        'search' => 'Search',
        'filter' => 'Filter',
        'export' => 'Export',
        'import' => 'Import',
        'back' => 'Back',
        'next' => 'Next',
        'previous' => 'Previous',
        'loading' => 'Loading...',
        'success' => 'Success',
        'error' => 'Error',
        'warning' => 'Warning',
        'info' => 'Information',
        
        // Messages
        'operation_successful' => 'Operation completed successfully',
        'operation_failed' => 'Operation failed',
        'please_try_again' => 'Please try again',
        'required_field' => 'This field is required',
        'invalid_email' => 'Please enter a valid email address',
        'password_too_short' => 'Password must be at least 6 characters',
    ],
    'bn' => [
        // Common
        'app_name' => 'পূজা ফান্ড',
        'app_subtitle' => 'ফান্ড ম্যানেজমেন্ট সিস্টেম',
        'language' => 'ভাষা',
        'english' => 'English',
        'bangla' => 'বাংলা',
        
        // Navigation
        'dashboard' => 'ড্যাশবোর্ড',
        'transactions' => 'লেনদেন',
        'transfers' => 'স্থানান্তর',
        'users' => 'ব্যবহারকারী',
        'reports' => 'রিপোর্ট',
        'logout' => 'লগআউট',
        
        // Login Page
        'login' => 'লগইন',
        'email_label' => 'ইমেইল ঠিকানা',
        'email_placeholder' => 'আপনার ইমেইল ঠিকানা লিখুন',
        'password_label' => 'পাসওয়ার্ড',
        'password_placeholder' => 'আপনার পাসওয়ার্ড লিখুন',
        'signin_btn' => 'সাইন ইন',
        'signing_in' => 'সাইন ইন হচ্ছে...',
        'footer_text' => 'পূজা ফান্ড ম্যানেজমেন্টের জন্য নিরাপদ লগইন',
        'invalid_credentials' => 'ভুল তথ্য প্রদান করা হয়েছে',
        'page_title_login' => 'লগইন - পূজা ফান্ড',
        
        // Dashboard
        'welcome_back' => 'স্বাগতম',
        'fund_overview' => 'আজকের জন্য আপনার ফান্ডের সংক্ষিপ্ত বিবরণ',
        'current_balance' => 'বর্তমান ব্যালেন্স',
        'total_collections' => 'মোট সংগ্রহ',
        'total_expenses' => 'মোট খরচ',
        'total_transactions' => 'মোট লেনদেন',
        'active_members' => 'সক্রিয় সদস্য',
        'this_month' => 'এই মাসে',
        'added_today' => 'আজ যোগ করা হয়েছে',
        'all_verified' => 'সবাই যাচাইকৃত',
        'surplus' => 'উদ্বৃত্ত',
        'deficit' => 'ঘাটতি',
        'collections' => 'সংগ্রহ',
        'expenses' => 'খরচ',
        'recent_transactions' => 'সাম্প্রতিক লেনদেন',
        'view_all' => 'সব দেখুন',
        'fund_balance' => 'ফান্ড ব্যালেন্স',
        'quick_actions' => 'দ্রুত কার্যক্রম',
        'add_transaction' => 'লেনদেন যোগ করুন',
        'view_all_transactions' => 'সব লেনদেন দেখুন',
        'manage_users' => 'ব্যবহারকারী পরিচালনা',
        'generate_report' => 'রিপোর্ট তৈরি করুন',
        'no_transactions_yet' => 'এখনও কোন লেনদেন নেই',
        'start_first_transaction' => 'আপনার প্রথম লেনদেন যোগ করে শুরু করুন',
        'quick_add_transaction' => 'দ্রুত লেনদেন যোগ করুন',
        'page_title_dashboard' => 'ড্যাশবোর্ড - পূজা ফান্ড',
        'page_title_transactions' => 'লেনদেন - পূজা ফান্ড',
        
        // Transactions
        'transaction_type' => 'লেনদেনের ধরন',
        'collection' => 'সংগ্রহ',
        'expense' => 'খরচ',
        'transfer' => 'স্থানান্তর',
        'amount' => 'পরিমাণ',
        'description' => 'বিবরণ',
        'brief_description' => 'সংক্ষিপ্ত বিবরণ',
        'date' => 'তারিখ',
        'category' => 'বিভাগ',
        'select_category' => 'বিভাগ নির্বাচন করুন',
        'mic' => 'মাইক',
        'added_by' => 'যোগকারী',
        
        // Transfers
        'transfer_funds' => 'অর্থ স্থানান্তর',
        'transfer_to' => 'স্থানান্তর করুন',
        'transfer_from' => 'থেকে স্থানান্তর',
        'transfer_amount' => 'স্থানান্তরের পরিমাণ',
        'transfer_description' => 'স্থানান্তরের বিবরণ',
        'transfer_date' => 'স্থানান্তরের তারিখ',
        'new_transfer' => 'নতুন স্থানান্তর',
        'transfer_history' => 'স্থানান্তরের ইতিহাস',
        'transfer_successful' => 'স্থানান্তর সফলভাবে সম্পন্ন হয়েছে',
        'transfer_failed' => 'স্থানান্তর ব্যর্থ হয়েছে',
        'select_user' => 'ব্যবহারকারী নির্বাচন করুন',
        'transfer_to_user' => 'ব্যবহারকারীর কাছে স্থানান্তর',
        'transfer_reason' => 'স্থানান্তরের কারণ',
        'confirm_transfer' => 'স্থানান্তর নিশ্চিত করুন',
        'page_title_transfers' => 'স্থানান্তর - পূজা ফান্ড',
        'cancel' => 'বাতিল',
        'save_changes' => 'পরিবর্তন সংরক্ষণ',
        'edit_transaction' => 'লেনদেন সম্পাদনা',
        'delete_transaction' => 'লেনদেন মুছুন',
        'confirm_delete_transaction' => 'এই লেনদেনটি মুছে ফেলার বিষয়ে আপনি কি নিশ্চিত?',
        'actions' => 'কার্যক্রম',
        'delete' => 'মুছুন',
        'confirm_approve_transfer' => 'আপনি কি নিশ্চিত যে এই স্থানান্তরটি অনুমোদন করতে চান?',
        'confirm_reject_transfer' => 'আপনি কি নিশ্চিত যে এই স্থানান্তরটি প্রত্যাখ্যান করতে চান?',
        'approve' => 'অনুমোদন',
        'reject' => 'প্রত্যাখ্যান',
        'approve_transfers' => 'স্থানান্তর অনুমোদন',
        'approve_transfers_desc' => 'অপেক্ষমাণ স্থানান্তর অনুরোধ পর্যালোচনা এবং অনুমোদন করুন',
        'pending_transfers' => 'অপেক্ষমাণ স্থানান্তর',
        'no_pending_transfers' => 'কোন অপেক্ষমাণ স্থানান্তর নেই',
        'all_transfers_processed' => 'সমস্ত স্থানান্তর অনুরোধ প্রক্রিয়া করা হয়েছে।',
        'profile' => 'প্রোফাইল',
        'my_profile' => 'আমার প্রোফাইল',
        'my_transactions' => 'আমার লেনদেন',
        'view_my_transaction_history' => 'আমার লেনদেনের ইতিহাস এবং ব্যালেন্স দেখুন',
        'my_balance' => 'আমার ব্যালেন্স',
        'my_collections' => 'আমার সংগ্রহ',
        'my_expenses' => 'আমার খরচ',
        'my_transfers' => 'আমার স্থানান্তর',
        'filter_transactions' => 'লেনদেন ফিল্টার করুন',
        'all' => 'সব',
        'year' => 'বছর',
        'reset' => 'রিসেট',
        'user' => 'ব্যবহারকারী',
        'type' => 'ধরন',
        'transfers' => 'স্থানান্তর',
        'from' => 'থেকে',
        'to' => 'প্রতি',
        'completed' => 'সম্পন্ন',
        'pending' => 'অপেক্ষমান',
        'cancelled' => 'বাতিল',
        'created_by' => 'তৈরি করেছেন',
        'confirm_delete' => 'মুছে ফেলার নিশ্চিতকরণ',
        
        // Users
                'add_user' => 'ব্যবহারকারী যোগ করুন',
        'full_name' => 'পূর্ণ নাম',
        'name_placeholder' => 'পূর্ণ নাম লিখুন',
        'email_address' => 'ইমেইল ঠিকানা',
        'role' => 'ভূমিকা',
        'manager' => 'ম্যানেজার',
        'member' => 'সদস্য',
        'password' => 'পাসওয়ার্ড',
        'password_placeholder' => 'পাসওয়ার্ড লিখুন',
        'add_user_btn' => 'ব্যবহারকারী যোগ করুন',
        'edit_user' => 'ব্যবহারকারী সম্পাদনা',
        'update_role' => 'ভূমিকা আপডেট',
        'delete_user' => 'ব্যবহারকারী মুছুন',
        'confirm_delete_user' => 'আপনি কি নিশ্চিত যে এই ব্যবহারকারীকে মুছে ফেলতে চান?',
        'page_title_users' => 'ব্যবহারকারী - পূজা ফান্ড',
        
        // Report page
        'reports' => 'রিপোর্ট',
        'financial_report' => 'আর্থিক রিপোর্ট',
        'from_date' => 'শুরুর তারিখ',
        'to_date' => 'শেষের তারিখ',
        'generate' => 'রিপোর্ট তৈরি করুন',
        'today' => 'আজ',
        'last_7_days' => 'গত ৭ দিন',
        'this_month' => 'এই মাস',
        'this_year' => 'এই বছর',
        'last_30_days' => 'গত ৩০ দিন',
        'last_year' => 'গত বছর',
        'summary' => 'সারসংক্ষেপ',
        'net_balance' => 'নিট ব্যালেন্স',
        'transaction_details' => 'লেনদেনের বিস্তারিত',
        'print_report' => 'রিপোর্ট প্রিন্ট করুন',
        'date_range' => 'সময়কাল',
        'net_balance' => 'নেট ব্যালেন্স',
        'transaction_details' => 'লেনদেনের বিস্তারিত',
        'transaction_type' => 'ধরন',
        'no_transactions' => 'কোন লেনদেন পাওয়া যায়নি',
        'no_transactions_period' => 'নির্বাচিত সময়ে কোন লেনদেন রেকর্ড করা হয়নি।',
        'back_to_dashboard' => 'ড্যাশবোর্ডে ফিরুন',
        'page_title_reports' => 'রিপোর্ট - পূজা ফান্ড',
        
        // Edit Transaction
        'edit_transaction' => 'লেনদেন সম্পাদনা',
        'update_transaction_details' => 'লেনদেনের বিস্তারিত আপডেট করুন',
        'back_to_transactions' => 'লেনদেনে ফিরুন',
        'update_transaction' => 'লেনদেন আপডেট করুন',
        
        // Transaction Messages
        'transaction_added_success' => 'লেনদেন সফলভাবে যোগ করা হয়েছে!',
        'error_adding_transaction' => 'লেনদেন যোগ করতে ত্রুটি',
        
        // Page Titles
        'page_title_installation' => 'ইনস্টলেশন - পূজা ফান্ড',
        'page_title_edit_transaction' => 'লেনদেন সম্পাদনা - পূজা ফান্ড',
        
        // Filter Labels
        'view_manage_transactions' => 'ফান্ড লেনদেন দেখুন এবং পরিচালনা করুন',
        'from_date' => 'শুরুর তারিখ',
        'to_date' => 'শেষের তারিখ',
        'apply_filter' => 'ফিল্টার প্রয়োগ করুন',
        'clear_filter' => 'ফিল্টার সাফ করুন',
        'select_year' => 'বছর নির্বাচন করুন',
        'year' => 'বছর',
        
        // Common Actions
        'add' => 'যোগ করুন',
        'edit' => 'সম্পাদনা',
        'update' => 'আপডেট',
        'close' => 'বন্ধ',
        'submit' => 'জমা দিন',
        'reset' => 'রিসেট',
        'search' => 'অনুসন্ধান',
        'filter' => 'ফিল্টার',
        'export' => 'এক্সপোর্ট',
        'import' => 'ইম্পোর্ট',
        'back' => 'পিছনে',
        'next' => 'পরবর্তী',
        'previous' => 'পূর্ববর্তী',
        'loading' => 'লোড হচ্ছে...',
        'success' => 'সফল',
        'error' => 'ত্রুটি',
        'warning' => 'সতর্কতা',
        'info' => 'তথ্য',
        
        // Messages
        'operation_successful' => 'কার্যক্রম সফলভাবে সম্পন্ন হয়েছে',
        'operation_failed' => 'কার্যক্রম ব্যর্থ হয়েছে',
        'please_try_again' => 'অনুগ্রহ করে আবার চেষ্টা করুন',
        'required_field' => 'এই ক্ষেত্রটি আবশ্যক',
        'invalid_email' => 'অনুগ্রহ করে একটি বৈধ ইমেইল ঠিকানা লিখুন',
        'password_too_short' => 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে',
    ]
];

// Get current language translations
$t = $translations[$lang];

// Helper function to get language switcher HTML
function getLanguageSwitcher($currentLang) {
    $currentPage = $_SERVER['PHP_SELF'];
    $queryParams = $_GET;
    
    $html = '<div class="language-switcher">';
    
    // English button
    $queryParams['lang'] = 'en';
    $enUrl = $currentPage . '?' . http_build_query($queryParams);
    $html .= '<a href="' . $enUrl . '" class="lang-btn ' . ($currentLang === 'en' ? 'active' : '') . '">English</a>';
    
    // Bangla button
    $queryParams['lang'] = 'bn';
    $bnUrl = $currentPage . '?' . http_build_query($queryParams);
    $html .= '<a href="' . $bnUrl . '" class="lang-btn ' . ($currentLang === 'bn' ? 'active' : '') . '">বাংলা</a>';
    
    $html .= '</div>';
    
    return $html;
}

// Helper function to get current language
function getCurrentLanguage() {
    return isset($_SESSION['lang']) ? $_SESSION['lang'] : 'en';
}

// Helper function to get translations for a language
function getTranslations($lang) {
    global $translations;
    return $translations[$lang] ?? $translations['en'];
}

// Helper function to get language-specific CSS class
function getLangClass($lang) {
    return $lang === 'bn' ? 'bangla-text' : '';
}
?>
