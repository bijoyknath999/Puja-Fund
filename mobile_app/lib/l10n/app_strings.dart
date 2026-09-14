/// Bilingual (English/Bengali) string table for the app, mirroring the
/// web app's lang.php key names where they overlap so both apps use the
/// same vocabulary. Missing keys fall back to English, then to the key
/// itself, so a missing translation never crashes the UI.
class AppStrings {
  AppStrings._();

  static const Map<String, Map<String, String>> _table = {
    'en': _en,
    'bn': _bn,
  };

  static String of(String lang, String key) {
    return _table[lang]?[key] ?? _table['en']![key] ?? key;
  }

  static const Map<String, String> _en = {
    // Common
    'app_name': 'Puja Fund',
    'language': 'Language',
    'english': 'English',
    'bangla': 'বাংলা',
    'cancel': 'Cancel',
    'delete': 'Delete',
    'edit': 'Edit',
    'save': 'Save',
    'close': 'Close',
    'confirm': 'Confirm',
    'create': 'Create',
    'apply': 'Apply',
    'clear': 'Clear',
    'retry': 'Retry',
    'required': 'Required',
    'loading': 'Loading...',
    'yes': 'Yes',
    'no': 'No',
    'all': 'All',
    'any': 'Any',
    'everyone': 'Everyone',
    'year': 'Year',
    'date': 'Date',
    'from_date': 'From date',
    'to_date': 'To date',
    'type': 'Type',
    'user': 'User',
    'amount': 'Amount',
    'description': 'Description',
    'category': 'Category',
    'none': 'None',
    'actions': 'Actions',
    'no_data_available': 'No data available',

    // Nav / shell
    'dashboard': 'Dashboard',
    'transactions': 'Transactions',
    // Shorter label just for the bottom nav bar / rail, where 5 columns
    // don't leave "Transactions" enough width and it wraps mid-word.
    'nav_transactions': 'History',
    'transfers': 'Transfers',
    'users': 'Users',
    'settings': 'Settings',
    'profile': 'Profile',
    'logout': 'Logout',
    'more': 'More',

    // Login
    'sign_in': 'Sign In',
    'sign_in_subtitle': 'Sign in to manage your fund',
    'email': 'Email',
    'password': 'Password',
    'email_required': 'Email is required',
    'password_required': 'Password is required',
    'signing_in': 'Signing In...',

    // Dashboard
    'welcome_back': 'Welcome back',
    'fund_overview': 'Here\'s your fund overview',
    'current_balance': 'Current Balance',
    'fund_balance': 'Fund Balance',
    'quick_add': 'Quick Add',
    'add_collection': 'Add Collection',
    'add_expense': 'Add Expense',
    'new_transfer': 'New Transfer',
    'recent_transactions': 'Recent Transactions',
    'your_recent_transactions': 'Your Recent Transactions',
    'no_transactions_yet': 'No transactions yet',
    'collection': 'Collection',
    'expense': 'Expense',
    'transfer': 'Transfer',
    'balance_is_negative': 'Balance is negative',

    // Transactions list / filters
    'filter_transactions': 'Filter Transactions',
    'no_transactions_match_filters': 'No transactions match these filters',
    'delete_transaction_q': 'Delete transaction?',
    'delete_transaction_confirm': 'This will permanently delete "{description}".',
    'delete_failed': 'Delete failed: {error}',

    // Transaction form
    'add_transaction': 'Add Transaction',
    'edit_transaction': 'Edit Transaction',
    'save_changes': 'Save Changes',
    'description_required': 'Description is required',
    'enter_valid_amount': 'Enter a valid amount',
    'balance_negative_title': 'Balance will go negative',
    'balance_will_go_negative':
        'This expense will take the fund balance negative ({balance}). Continue?',
    'continue_label': 'Continue',

    // Transfers
    'new_transfer_request': 'New Transfer Request',
    'request_transfer_desc': 'Request a transfer to another member',
    'transfer_approval_note':
        'A transfer request needs manager approval before it appears in transactions.',
    'no_transfers_yet': 'No transfers yet',
    'approve': 'Approve',
    'reject': 'Reject',
    'delete_transfer_q': 'Delete transfer?',
    'delete_transfer_desc': 'This removes the transfer and its linked transactions.',
    'approve_failed': 'Approve failed: {error}',
    'reject_failed': 'Reject failed: {error}',
    'transfer_to': 'Transfer to',
    'please_choose_recipient': 'Please choose a recipient',
    'submit_request': 'Submit Request',
    'transfer_submitted_pending': 'Transfer request submitted - pending manager approval.',
    'completed': 'Completed',
    'pending': 'Pending',
    'cancelled': 'Cancelled',
    'rejected': 'Rejected',

    // Users
    'add_user': 'Add User',
    'name': 'Name',
    'role': 'Role',
    'manager': 'Manager',
    'member': 'Member',
    'at_least_6_chars': 'At least 6 characters',
    'this_is_you': 'This is you',
    'change_role_q': 'Change role?',
    'make_manager_q': 'Make {name} a manager?',
    'make_member_q': 'Make {name} a member?',
    'make_manager': 'Make Manager',
    'make_member': 'Make Member',
    'delete_user_q': 'Delete user?',
    'delete_user_confirm': 'This permanently deletes {name} and their transactions.',
    'update_failed': 'Update failed: {error}',
    'transactions_stat': 'Transactions',
    'collections_stat': 'Collections',
    'expenses_stat': 'Expenses',

    // Settings
    'active_year': 'Active Year',
    'active_year_desc':
        'New transactions default to this year, and this is the year shown by default across the app.',
    'change_active_year': 'Change Active Year',
    'start_new_year': 'Start a New Year',
    'start_new_year_desc':
        'Enter a year that has not been used before to start fresh, keeping all older data intact.',
    'new_year_placeholder': 'e.g. 2027',
    'enter_valid_year': 'Enter a valid 4-digit year',
    'year_has_data_error': 'That year already has data - use "Switch active year" instead.',
    'save_settings': 'Save',
    'active_year_switched': 'Active year switched to {year}.',
    'new_year_started': 'Started new year {year} as the active year.',

    // Profile
    'my_profile': 'My Profile',
    'my_transactions': 'My Transactions',
    'view_my_transaction_history': 'My transaction history and balance',
    'my_balance': 'My Balance',
    'my_collections': 'My Collections',
    'my_expenses': 'My Expenses',
    'log_out': 'Log Out',
    'log_out_q': 'Log out?',
    'log_out_confirm_desc': 'You will need to sign in again to continue.',
    'user_id': 'User ID',
  };

  static const Map<String, String> _bn = {
    // Common
    'app_name': 'পূজা ফান্ড',
    'language': 'ভাষা',
    'english': 'English',
    'bangla': 'বাংলা',
    'cancel': 'বাতিল',
    'delete': 'মুছুন',
    'edit': 'সম্পাদনা',
    'save': 'সংরক্ষণ',
    'close': 'বন্ধ',
    'confirm': 'নিশ্চিত করুন',
    'create': 'তৈরি করুন',
    'apply': 'প্রয়োগ করুন',
    'clear': 'মুছে ফেলুন',
    'retry': 'আবার চেষ্টা করুন',
    'required': 'আবশ্যক',
    'loading': 'লোড হচ্ছে...',
    'yes': 'হ্যাঁ',
    'no': 'না',
    'all': 'সব',
    'any': 'যেকোনো',
    'everyone': 'সবাই',
    'year': 'বছর',
    'date': 'তারিখ',
    'from_date': 'শুরুর তারিখ',
    'to_date': 'শেষের তারিখ',
    'type': 'ধরন',
    'user': 'ব্যবহারকারী',
    'amount': 'পরিমাণ',
    'description': 'বিবরণ',
    'category': 'বিভাগ',
    'none': 'কোনটি না',
    'actions': 'কার্যক্রম',
    'no_data_available': 'কোন তথ্য নেই',

    // Nav / shell
    'dashboard': 'ড্যাশবোর্ড',
    'transactions': 'লেনদেন',
    'nav_transactions': 'লেনদেন',
    'transfers': 'স্থানান্তর',
    'users': 'ব্যবহারকারী',
    'settings': 'সেটিংস',
    'profile': 'প্রোফাইল',
    'logout': 'লগআউট',
    'more': 'আরও',

    // Login
    'sign_in': 'সাইন ইন',
    'sign_in_subtitle': 'আপনার ফান্ড পরিচালনা করতে সাইন ইন করুন',
    'email': 'ইমেইল',
    'password': 'পাসওয়ার্ড',
    'email_required': 'ইমেইল আবশ্যক',
    'password_required': 'পাসওয়ার্ড আবশ্যক',
    'signing_in': 'সাইন ইন হচ্ছে...',

    // Dashboard
    'welcome_back': 'স্বাগতম',
    'fund_overview': 'আপনার ফান্ডের সংক্ষিপ্ত বিবরণ',
    'current_balance': 'বর্তমান ব্যালেন্স',
    'fund_balance': 'ফান্ড ব্যালেন্স',
    'quick_add': 'দ্রুত যোগ',
    'add_collection': 'সংগ্রহ যোগ করুন',
    'add_expense': 'খরচ যোগ করুন',
    'new_transfer': 'নতুন স্থানান্তর',
    'recent_transactions': 'সাম্প্রতিক লেনদেন',
    'your_recent_transactions': 'আপনার সাম্প্রতিক লেনদেন',
    'no_transactions_yet': 'এখনও কোন লেনদেন নেই',
    'collection': 'সংগ্রহ',
    'expense': 'খরচ',
    'transfer': 'স্থানান্তর',
    'balance_is_negative': 'ব্যালেন্স ঋণাত্মক',

    // Transactions list / filters
    'filter_transactions': 'লেনদেন ফিল্টার করুন',
    'no_transactions_match_filters': 'এই ফিল্টারের সাথে কোন লেনদেন মেলেনি',
    'delete_transaction_q': 'লেনদেন মুছবেন?',
    'delete_transaction_confirm': 'এটি স্থায়ীভাবে "{description}" মুছে ফেলবে।',
    'delete_failed': 'মুছতে ব্যর্থ: {error}',

    // Transaction form
    'add_transaction': 'লেনদেন যোগ করুন',
    'edit_transaction': 'লেনদেন সম্পাদনা',
    'save_changes': 'পরিবর্তন সংরক্ষণ',
    'description_required': 'বিবরণ আবশ্যক',
    'enter_valid_amount': 'একটি বৈধ পরিমাণ লিখুন',
    'balance_negative_title': 'ব্যালেন্স ঋণাত্মক হবে',
    'balance_will_go_negative':
        'এই খরচের ফলে ফান্ড ব্যালেন্স ঋণাত্মক হয়ে যাবে ({balance})। চালিয়ে যাবেন?',
    'continue_label': 'চালিয়ে যান',

    // Transfers
    'new_transfer_request': 'নতুন স্থানান্তর অনুরোধ',
    'request_transfer_desc': 'অন্য সদস্যের কাছে স্থানান্তরের অনুরোধ করুন',
    'transfer_approval_note':
        'লেনদেনে প্রদর্শিত হওয়ার আগে স্থানান্তর অনুরোধের জন্য ম্যানেজারের অনুমোদন প্রয়োজন।',
    'no_transfers_yet': 'এখনও কোন স্থানান্তর নেই',
    'approve': 'অনুমোদন',
    'reject': 'প্রত্যাখ্যান',
    'delete_transfer_q': 'স্থানান্তর মুছবেন?',
    'delete_transfer_desc': 'এটি স্থানান্তর এবং এর সংযুক্ত লেনদেনগুলি সরিয়ে দেবে।',
    'approve_failed': 'অনুমোদন ব্যর্থ: {error}',
    'reject_failed': 'প্রত্যাখ্যান ব্যর্থ: {error}',
    'transfer_to': 'স্থানান্তর করুন',
    'please_choose_recipient': 'একজন গ্রহীতা নির্বাচন করুন',
    'submit_request': 'অনুরোধ জমা দিন',
    'transfer_submitted_pending':
        'স্থানান্তর অনুরোধ জমা দেওয়া হয়েছে - ম্যানেজারের অনুমোদনের অপেক্ষায়।',
    'completed': 'সম্পন্ন',
    'pending': 'অপেক্ষমান',
    'cancelled': 'বাতিল',
    'rejected': 'প্রত্যাখ্যাত',

    // Users
    'add_user': 'ব্যবহারকারী যোগ করুন',
    'name': 'নাম',
    'role': 'ভূমিকা',
    'manager': 'ম্যানেজার',
    'member': 'সদস্য',
    'at_least_6_chars': 'কমপক্ষে ৬ অক্ষর',
    'this_is_you': 'এটি আপনি',
    'change_role_q': 'ভূমিকা পরিবর্তন করবেন?',
    'make_manager_q': '{name}-কে ম্যানেজার করবেন?',
    'make_member_q': '{name}-কে সদস্য করবেন?',
    'make_manager': 'ম্যানেজার করুন',
    'make_member': 'সদস্য করুন',
    'delete_user_q': 'ব্যবহারকারী মুছবেন?',
    'delete_user_confirm': 'এটি স্থায়ীভাবে {name} এবং তার লেনদেন মুছে ফেলবে।',
    'update_failed': 'আপডেট ব্যর্থ: {error}',
    'transactions_stat': 'লেনদেন',
    'collections_stat': 'সংগ্রহ',
    'expenses_stat': 'খরচ',

    // Settings
    'active_year': 'সক্রিয় বছর',
    'active_year_desc':
        'নতুন লেনদেন ডিফল্টভাবে এই বছরে যোগ হবে, এবং পুরো অ্যাপে ডিফল্টভাবে এই বছরের তথ্য দেখানো হবে।',
    'change_active_year': 'সক্রিয় বছর পরিবর্তন করুন',
    'start_new_year': 'নতুন বছর শুরু করুন',
    'start_new_year_desc':
        'আগে ব্যবহার হয়নি এমন একটি বছর লিখুন নতুনভাবে শুরু করতে, পুরনো সব তথ্য অক্ষত রেখে।',
    'new_year_placeholder': 'যেমন ২০২৭',
    'enter_valid_year': 'একটি বৈধ ৪-সংখ্যার বছর লিখুন',
    'year_has_data_error': 'এই বছরে ইতিমধ্যে তথ্য আছে - পরিবর্তে "সক্রিয় বছর পরিবর্তন করুন" ব্যবহার করুন।',
    'save_settings': 'সংরক্ষণ করুন',
    'active_year_switched': 'সক্রিয় বছর {year}-এ পরিবর্তন করা হয়েছে।',
    'new_year_started': 'নতুন বছর {year} সক্রিয় বছর হিসেবে শুরু করা হয়েছে।',

    // Profile
    'my_profile': 'আমার প্রোফাইল',
    'my_transactions': 'আমার লেনদেন',
    'view_my_transaction_history': 'আমার লেনদেনের ইতিহাস এবং ব্যালেন্স',
    'my_balance': 'আমার ব্যালেন্স',
    'my_collections': 'আমার সংগ্রহ',
    'my_expenses': 'আমার খরচ',
    'log_out': 'লগ আউট',
    'log_out_q': 'লগআউট করবেন?',
    'log_out_confirm_desc': 'চালিয়ে যেতে আপনাকে আবার সাইন ইন করতে হবে।',
    'user_id': 'ব্যবহারকারী আইডি',
  };
}
