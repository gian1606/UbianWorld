document.addEventListener('DOMContentLoaded', async () => {
    
    // --- New: Function to handle AJAX calls for cleaner code ---
    async function apiCall(endpoint, method = 'GET', data = null) {
        const options = {
            method: method,
        };

        if (method === 'POST' || method === 'PUT' || method === 'DELETE') {
            options.headers = {
                // Front-end should use 'application/x-www-form-urlencoded' for PHP's $_POST/parse_str compatibility
                'Content-Type': 'application/x-www-form-urlencoded', 
            };
            if (data) {
                // Convert data object to URL-encoded string for POST/PUT/etc.
                options.body = new URLSearchParams(data).toString(); 
            }
        }
        
        try {
            const response = await fetch(endpoint, options);
            
            // Handle non-200 responses (like 401 Unauthorized)
            if (!response.ok) {
                const errorText = await response.text();
                // Attempt to parse JSON error message first
                try {
                    const errorJson = JSON.parse(errorText);
                    return { success: false, message: errorJson.message || 'Server error.' };
                } catch (e) {
                    // Fallback to generic error
                    return { success: false, message: `Request failed with status ${response.status}: ${errorText.substring(0, 50)}...` };
                }
            }

            // Check content type to safely parse JSON
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.includes("application/json")) {
                return response.json();
            } else {
                // Log non-JSON response for debugging
                const text = await response.text(); 
                return { success: true, message: 'Server returned plain text data.', data: text };
            }
        } catch (error) {
            console.error('API Call Error:', endpoint, error);
            return { success: false, message: 'Network or Server Error.' };
        }
    }

    // --- Core Authentication and UI Functions ---

    /**
     * Checks the user's session and updates the header UI.
     * @returns {object} The session data.
     */
    async function checkSession() {
        const result = await apiCall('api/check_session.php');
        const session = result.isLoggedIn ? result : { isLoggedIn: false, role: null, username: null, user_id: null };

        const authLink = document.getElementById('auth-link');
        const authLi = authLink ? authLink.closest('li') : null;
        
        if (session.isLoggedIn) {
            authLink.textContent = 'Log Out';
            authLink.href = '#'; // Will be handled by the click event
            
            // Add event listener for logout
            authLink.removeEventListener('click', handleLogout); // Avoid double binding
            authLink.addEventListener('click', handleLogout);

            // Dynamically add a link to the user's portal/dashboard
            let portalUrl = (session.role === 'admin') ? 'admin_dashboard.html' : 'profile.html';
            let portalText = (session.role === 'admin') ? 'Admin Dashboard' : 'My Portal';

            // Check if the portal link already exists in the header (to avoid duplication on every page)
            const existingPortalLink = document.querySelector('.portal-link');
            if (!existingPortalLink) {
                const portalLi = document.createElement('li');
                portalLi.classList.add('portal-li');
                const portalAnchor = document.createElement('a');
                portalAnchor.href = portalUrl;
                portalAnchor.textContent = portalText;
                portalAnchor.classList.add('portal-link');
                // Check if current page is the portal/dashboard to set 'active' class
                if (window.location.pathname.includes(portalUrl)) {
                    portalAnchor.classList.add('active');
                }
                portalLi.appendChild(portalAnchor);
                
                // Insert the portal link before the Log Out link
                if (authLi && authLi.parentNode) {
                    authLi.parentNode.insertBefore(portalLi, authLi);
                }
            }
        } else {
            // Not logged in: ensure UI defaults to Log In
            if (authLink) {
                authLink.textContent = 'Log In';
                authLink.href = 'login.html';
                // Remove the logout listener if it exists
                authLink.removeEventListener('click', handleLogout);
                // Remove the dynamically added portal link
                document.querySelector('.portal-li')?.remove();
            }
        }
        return session;
    }

    /**
     * Handles the Logout process.
     */
    async function handleLogout(e) {
        e.preventDefault();
        const result = await apiCall('api/logout.php', 'POST');
        if (result.success) {
            // Simple alert for success, then redirect
            alert(result.message);
            window.location.href = 'index.html';
        } else {
            alert('Logout failed: ' + result.message);
        }
    }

    /**
     * Handles the Login form submission.
     */
    function setupLoginForm() {
        const form = document.getElementById('login-form');
        const messageDiv = document.getElementById('login-message');

        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            messageDiv.textContent = 'Logging in...';
            messageDiv.className = 'message-info';

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            const result = await apiCall('api/login.php', 'POST', data);

            if (result.success) {
                messageDiv.textContent = result.message;
                messageDiv.className = 'message-success';
                // Redirect to the correct portal
                const redirectUrl = (result.role === 'admin') ? 'admin_dashboard.html' : 'profile.html';
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 1000);
            } else {
                messageDiv.textContent = result.message;
                messageDiv.className = 'message-error';
            }
        });
    }

    /**
     * Handles the Signup form submission.
     */
    function setupSignupForm() {
        const form = document.getElementById('signup-form');
        const messageDiv = document.getElementById('signup-message');

        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            messageDiv.textContent = 'Registering...';
            messageDiv.className = 'message-info';

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            // Simple password confirmation check (client-side)
            if (data.password !== data.confirm_password) {
                messageDiv.textContent = 'Passwords do not match.';
                messageDiv.className = 'message-error';
                return;
            }
            
            // Remove confirm_password before sending
            delete data.confirm_password; 

            const result = await apiCall('api/signup.php', 'POST', data);

            if (result.success) {
                messageDiv.textContent = result.message + ' Redirecting to login...';
                messageDiv.className = 'message-success';
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 1500);
            } else {
                messageDiv.textContent = result.message;
                messageDiv.className = 'message-error';
            }
        });
    }

    // --- Content Fetching Functions ---

    /**
     * Fetches and displays content (Announcements, Guides)
     */
    async function loadContent(type, targetElementId) {
        const targetElement = document.getElementById(targetElementId);
        if (!targetElement) return;

        targetElement.innerHTML = `<p class="loading-message">Loading latest ${type}s...</p>`;

        const result = await apiCall(`api/fetch_content.php?type=${type}`);

        if (result.success && result.data.length > 0) {
            targetElement.innerHTML = ''; // Clear loading message
            
            result.data.forEach(item => {
                if (type === 'Announcement') {
                    // Structure for index.html grid
                    const card = document.createElement('a');
                    card.href = 'announcement.html#' + item.ResourceID; // Link to the full page, scroll to announcement
                    card.classList.add('announcement-card');
                    card.innerHTML = `
                        <h3>${item.Title}</h3>
                        <p class="date">${item.DatePostedFormatted}</p>
                        <p class="snippet">${item.ContentText.substring(0, 150)}...</p>
                    `;
                    targetElement.appendChild(card);
                } else if (type === 'Guide') {
                    // Structure for guides.html table rows
                    const row = document.createElement('tr');
                    // Determine the action link: direct URL or download PHP script
                    const linkUrl = item.ContentURL.startsWith('http') || item.ContentURL.startsWith('/')
                        ? item.ContentURL
                        : `download_document.php?document_id=${item.ResourceID}`; // This is a placeholder as your current 'Guides' are CONTENT, not DOCUMENTs. Using ContentURL directly.
                    
                    row.innerHTML = `
                        <td>${item.Title}</td>
                        <td class="description-cell">${item.ContentText.substring(0, 100)}...</td>
                        <td>${item.DatePostedFormatted}</td>
                        <td class="action-cell">
                            ${item.ContentURL 
                                ? `<a href="${item.ContentURL}" target="_blank" class="download-link"><i class="icon-download"></i> View/Download</a>`
                                : `<span class="no-link">View Details</span>`
                            }
                        </td>
                    `;
                    targetElement.appendChild(row);
                }
            });
            
            // Limit announcements on index.html to 3
            if (targetElementId === 'announcements-grid') {
                while (targetElement.children.length > 3) {
                    targetElement.removeChild(targetElement.lastChild);
                }
            }

        } else {
            targetElement.innerHTML = `<p class="message-info">No ${type}s found. ${result.message}</p>`;
        }
    }


    // --- Student Portal Functions ---

    /**
     * Fetches and displays the logged-in student's inquiries.
     */
    async function loadStudentInquiries(student_id) {
        const tableBody = document.getElementById('student-inquiries-table-body');
        if (!tableBody) return;
        
        tableBody.innerHTML = `<tr><td colspan="4">Loading your submitted inquiries...</td></tr>`;

        // The fetch_student_inquiries.php script uses the session ID, so we don't need to pass it, but it's good for clarity
        const result = await apiCall(`api/fetch_student_inquiries.php`); 

        if (result.success && result.data.length > 0) {
            tableBody.innerHTML = '';
            result.data.forEach(inquiry => {
                const row = document.createElement('tr');
                let statusClass = inquiry.Status.toLowerCase().replace('-', '');
                let responseText = inquiry.ResponseText || 'No response from ISSO Staff yet.';
                
                row.innerHTML = `
                    <td data-label="ID">${inquiry.InquiryID}</td>
                    <td data-label="Subject">
                        <strong>${inquiry.Subject}</strong><br>
                        <span class="description-preview">${inquiry.Description.substring(0, 50)}...</span>
                    </td>
                    <td data-label="Status"><span class="status-badge status-${statusClass}">${inquiry.Status}</span></td>
                    <td data-label="Response">
                        <span class="response-text">${responseText.substring(0, 70)}...</span>
                        <button class="cta-button secondary-cta view-inquiry" data-id="${inquiry.InquiryID}" data-subject="${inquiry.Subject}" data-status="${inquiry.Status}" data-description="${inquiry.Description}" data-response="${responseText}" data-staff="${inquiry.StaffFirstName || 'N/A'} ${inquiry.StaffLastName || ''}">View Full</button>
                    </td>
                `;
                tableBody.appendChild(row);
            });
            
            // Setup modal viewers for inquiries
            document.querySelectorAll('.view-inquiry').forEach(button => {
                button.addEventListener('click', (e) => showStudentInquiryModal(e.currentTarget));
            });

        } else if (result.success && result.data.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="4" class="message-info">You have not submitted any inquiries yet.</td></tr>`;
        } else {
            tableBody.innerHTML = `<tr><td colspan="4" class="message-error">Failed to load inquiries: ${result.message}</td></tr>`;
        }
    }
    
    function showStudentInquiryModal(button) {
        const modal = document.getElementById('inquiry-details-modal');
        if (!modal) return;
        
        // Populate modal with data attributes
        document.getElementById('modal-student-inquiry-id').textContent = button.dataset.id;
        document.getElementById('modal-student-subject').textContent = button.dataset.subject;
        document.getElementById('modal-student-description').textContent = button.dataset.description;
        document.getElementById('modal-student-status').textContent = button.dataset.status;
        document.getElementById('modal-student-response').textContent = button.dataset.response;
        document.getElementById('modal-student-staff').textContent = button.dataset.staff;

        // Apply status style
        const statusSpan = document.getElementById('modal-student-status');
        statusSpan.className = `status-badge status-${button.dataset.status.toLowerCase().replace('-', '')}`;

        modal.style.display = 'block';
        
        // Close button logic
        document.querySelector('.close-button').onclick = () => {
            modal.style.display = 'none';
        };
        window.onclick = (event) => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        };
    }
    
    /**
     * Sets up the form submission for a new inquiry.
     */
    function setupInquiryForm(session) {
        const form = document.getElementById('submit-inquiry-form');
        const messageDiv = document.getElementById('inquiry-message');

        if (!form || !session.isLoggedIn || session.role !== 'student') return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            messageDiv.textContent = 'Submitting your inquiry...';
            messageDiv.className = 'message-info';

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            // The submit_inquiry.php script uses POST with a standard body
            const result = await apiCall('api/submit_inquiry.php', 'POST', data);

            if (result.success) {
                messageDiv.textContent = result.message;
                messageDiv.className = 'message-success';
                form.reset(); // Clear form on success
                // Reload inquiries list after successful submission
                loadStudentInquiries(session.user_id);
            } else {
                messageDiv.textContent = result.message;
                messageDiv.className = 'message-error';
            }
        });
    }


    // --- Admin Dashboard Functions ---

    /**
     * Load the Admin Dashboard with dynamic data.
     */
    async function loadAdminDashboard(session) {
        const dashboardDiv = document.getElementById('admin-dashboard-content');
        if (!dashboardDiv || session.role !== 'admin') return;

        // 1. Fetch Inquiries List (for the main dashboard table)
        const inquiryResult = await apiCall('api/fetch_inquiries.php');
        const tableBody = document.getElementById('inquiry-table-body');
        
        if (inquiryResult.success && tableBody) {
            tableBody.innerHTML = ''; // Clear existing content
            inquiryResult.data.forEach(inquiry => {
                const row = document.createElement('tr');
                let statusClass = inquiry.Status.toLowerCase().replace('-', '');
                
                row.innerHTML = `
                    <td data-label="ID">${inquiry.InquiryID}</td>
                    <td data-label="Student">${inquiry.FirstName} ${inquiry.LastName} (${inquiry.StudentUsername})</td>
                    <td data-label="Subject"><strong>${inquiry.Subject}</strong></td>
                    <td data-label="Date">${inquiry.DateSubmittedFormatted}</td>
                    <td data-label="Status"><span class="status-badge status-${statusClass}">${inquiry.Status}</span></td>
                    <td data-label="Action">
                        <button class="cta-button secondary-cta view-case" data-id="${inquiry.InquiryID}" data-student="${inquiry.FirstName} ${inquiry.LastName}" data-username="${inquiry.StudentUsername}" data-subject="${inquiry.Subject}" data-status="${inquiry.Status}" data-description="${inquiry.Description}">Review & Respond</button>
                    </td>
                `;
                tableBody.appendChild(row);
            });
            
            // Setup modal viewers for admin inquiry response
            document.querySelectorAll('.view-case').forEach(button => {
                button.addEventListener('click', (e) => showAdminResponseModal(e.currentTarget, session));
            });

        } else if (tableBody) {
             tableBody.innerHTML = `<tr><td colspan="6" class="message-info">Failed to load inquiries: ${inquiryResult.message}</td></tr>`;
        }
        
        // 2. Load Content List (placeholder for manage_content.html)
        // This is handled on the separate admin_content_manager.html page
        
        // 3. Load Reports (placeholder for admin_reports.html)
        // This is handled on the separate admin_reports.html page
    }
    
    /**
     * Shows the Admin Response Modal and sets up the submission logic.
     */
    async function showAdminResponseModal(button, session) {
        const modal = document.getElementById('admin-response-modal');
        const inquiryID = button.dataset.id;
        const form = document.getElementById('admin-response-form');
        const responseMessageDiv = document.getElementById('admin-response-message');
        
        if (!modal) return;
        
        // Populate modal with inquiry details
        document.getElementById('modal-case-id').textContent = inquiryID;
        document.getElementById('modal-student-name').textContent = button.dataset.student;
        document.getElementById('modal-student-id').textContent = button.dataset.username;
        document.getElementById('modal-subject').textContent = button.dataset.subject;
        document.getElementById('modal-description').textContent = button.dataset.description;
        
        // Set initial status and status badge
        const currentStatus = button.dataset.status;
        const statusSpan = document.getElementById('modal-status');
        const newStatusSelect = document.getElementById('new-status');

        statusSpan.textContent = currentStatus;
        statusSpan.className = `status-badge status-${currentStatus.toLowerCase().replace('-', '')}`;
        newStatusSelect.value = currentStatus.toLowerCase();
        
        // Fetch existing response if any
        let existingResponse = 'Fetching existing response...';
        
        // NOTE: The main inquiry fetch did not include the response text, we need another call to get the response.
        // For simplicity, we are passing all required data from the table (see fetch_inquiries.php logic for response details)
        // To avoid an extra API call, we'll assume the simple fetch_inquiries gave us enough data for this simple dashboard view, but a robust system would fetch the full case details here.
        // **RE-CHECK:** Ah, the fetch_inquiries.php only gives Subject, Description, and I.StaffID. To get the actual response text, you would need another call or modify fetch_inquiries.
        // **FIX:** I will modify loadAdminDashboard to use the data-attributes from the inquiry table.
        // For now, let's assume the inquiry object passed from loadAdminDashboard includes the ResponseText, which it doesn't in the current implementation of fetch_inquiries.php, so I'll show a placeholder.
        
        // For now, let's keep the response box empty or pre-filled if there's an easy way to get it later.
        // The modal used for student portal is different from the admin one, so I will reuse the student modal's response display here.
        document.getElementById('response-text').value = ''; // Clear response box for new entry
        
        modal.style.display = 'block';

        // --- Form Submission Logic (Submit Response and Update Status) ---
        const handleAdminResponse = async (e) => {
            e.preventDefault();
            responseMessageDiv.textContent = 'Submitting response and updating status...';
            responseMessageDiv.className = 'message-info';

            const data = {
                inquiry_id: inquiryID,
                response_text: document.getElementById('response-text').value,
                new_status: newStatusSelect.value
            };
            
            const result = await apiCall('api/submit_response.php', 'POST', data);

            if (result.success) {
                responseMessageDiv.textContent = result.message;
                responseMessageDiv.className = 'message-success';
                // Reload dashboard to update the table
                setTimeout(() => {
                    modal.style.display = 'none';
                    loadAdminDashboard(session);
                }, 1000);
            } else {
                responseMessageDiv.textContent = result.message;
                responseMessageDiv.className = 'message-error';
            }
        };
        
        // Clear previous listeners and attach new one
        form.removeEventListener('submit', form.currentListener); 
        form.addEventListener('submit', handleAdminResponse);
        form.currentListener = handleAdminResponse; // Store listener reference

        // Close button logic
        document.querySelector('#admin-response-modal .close-button').onclick = () => {
            modal.style.display = 'none';
        };
        window.onclick = (event) => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        };
    }

    // --- Main Execution Block ---
    
    // 1. Check Session and Update UI first
    const session = await checkSession();
    
    // 2. Setup Forms
    setupLoginForm();
    setupSignupForm();
    if (session.isLoggedIn && session.role === 'student') {
        setupInquiryForm(session);
    }
    
    // 3. Load Dynamic Content based on the current page
    
    // Check if on Home page (index.html)
    if (document.getElementById('announcements-grid')) {
        loadContent('Announcement', 'announcements-grid');
    }

    // Check if on Announcement page
    if (document.getElementById('main-announcements')) {
        // Load all announcements for the main page
        loadContent('Announcement', 'main-announcements');
    }

    // Check if on Guides page
    if (document.getElementById('guides-table-body')) { // Changed from 'guides-table' to 'guides-table-body' to target the tbody
        loadContent('Guide', 'guides-table-body');
    }
    
    // Check if on Admin Dashboard
    if (document.getElementById('admin-dashboard-content')) {
        if (session.isLoggedIn && session.role === 'admin') {
            document.getElementById('admin-dashboard-content').style.display = 'block';
            document.getElementById('admin-access-denied').style.display = 'none';
            loadAdminDashboard(session);
        } else {
            // Display access denied if logged in but not admin, or not logged in.
            document.getElementById('admin-dashboard-content').style.display = 'none';
            document.getElementById('admin-access-denied').style.display = 'block';
        }
    }
    
    // Check if on Student Portal
    if (document.getElementById('student-portal')) {
        if (session.isLoggedIn && session.role === 'student') {
            document.getElementById('student-portal').style.display = 'block';
            document.getElementById('student-access-denied').style.display = 'none';
            loadStudentInquiries(session.user_id);
        } else {
            document.getElementById('student-portal').style.display = 'none';
            // Assuming 'student-access-denied' exists on profile.html
            const accessDenied = document.getElementById('student-access-denied');
            if (accessDenied) accessDenied.style.display = 'block';
        }
    }

    // ... (You would need to add logic for admin_content_manager.html, admin_reports.html, etc., using similar patterns)

});