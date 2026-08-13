/**
 * Client-Side Form Validation Script
 * 
 * Provides real-time field validation for contact and registration forms.
 * Validates full name format, email syntax, phone length/digits, and submission state.
 */

// Cache DOM error message elements for quick feedback rendering
var nameError    = document.getElementById('name-error');
var emailError   = document.getElementById('email-error');
var phoneError   = document.getElementById('phone-error');
var messageError = document.getElementById('message-error');
var submitError  = document.getElementById('submit-error');

/**
 * Validates candidate full name input.
 * Ensures the input is not empty and contains first and last name separated by a space.
 * 
 * @returns {boolean} True if valid name format, false otherwise.
 */
function validateName() {
    var nameField = document.getElementById('contact-name');
    if (!nameField) return true; // Skip if field is absent on current view
    var name = nameField.value.trim();

    // Check if input is empty
    if (name.length === 0) {
        if (nameError) nameError.innerHTML = "Please enter full name!";
        return false;
    }
    
    // Regex matching First Name + Single Space + Last Name
    if (!name.match(/^[A-Za-z]+\s{1}[A-Za-z]+$/)) {
        if (nameError) nameError.innerHTML = "Write full name (First & Last)!";
        return false;
    }

    // Success feedback icon
    if (nameError) nameError.innerHTML = '<i class="fas fa-check-circle text-success"></i>';
    return true;
}

/**
 * Validates email input format.
 * Checks for standard user@domain.tld structure.
 * 
 * @returns {boolean} True if valid email format, false otherwise.
 */
function validateEmail() {
    var emailField = document.getElementById('contact-email');
    if (!emailField) return true;
    var email = emailField.value.trim();

    // Check if email input is empty
    if (email.length === 0) {
        if (emailError) emailError.innerHTML = "Please enter email!";
        return false;
    }

    // Standard email validation regular expression
    var emailRegex = /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-zA-Z]{2,}$/;
    if (!email.match(emailRegex)) {
        if (emailError) emailError.innerHTML = "Enter a valid email address!";
        return false;
    }

    // Success feedback icon
    if (emailError) emailError.innerHTML = '<i class="fas fa-check-circle text-success"></i>';
    return true;
}

/**
 * Validates phone number input format.
 * Ensures input is exactly 10 numerical digits.
 * 
 * @returns {boolean} True if valid phone format, false otherwise.
 */
function validatePhone() {
    var phoneField = document.getElementById('contact-phone');
    if (!phoneField) return true;
    var phone = phoneField.value.trim();

    // Check if phone number input is empty
    if (phone.length === 0) {
        if (phoneError) phoneError.innerHTML = "Please enter phone number!";
        return false;
    }

    // Match 10 numerical digits
    if (!phone.match(/^[0-9]{10}$/)) {
        if (phoneError) phoneError.innerHTML = "Enter 10-digit phone number!";
        return false;
    }

    // Success feedback icon
    if (phoneError) phoneError.innerHTML = '<i class="fas fa-check-circle text-success"></i>';
    return true;
}

/**
 * Validates entire form prior to form submission.
 * Displays overall submission error message if any field fails validation.
 * 
 * @returns {boolean} True if all fields are valid, false to block submission.
 */
function validateForm() {
    if (!validateName() || !validateEmail() || !validatePhone()) {
        if (submitError) {
            submitError.style.display = 'block';
            submitError.innerHTML = 'Please fix errors above before submitting';
            // Auto-hide submission error message after 3 seconds
            setTimeout(function() {
                submitError.style.display = 'none';            
            }, 3000);
        }
        return false;
    }
    return true;
}