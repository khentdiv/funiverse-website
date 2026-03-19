<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real Google Sign-In · SMS via email.com</title>
    <!-- Google Identity Services library (official) -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, Roboto, sans-serif;
        }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #eef4ff 0%, #d9e4f5 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .app-container {
            max-width: 900px;
            width: 100%;
            background: rgba(255,255,255,0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 3.5rem;
            box-shadow: 0 30px 60px -15px #1f3f70;
            padding: 2.5rem 2.2rem;
        }
        .header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 2.2rem;
        }
        .logo-area {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        .sms-badge {
            background: #102a51;
            color: white;
            padding: 0.3rem 1.4rem;
            border-radius: 60px;
            font-weight: 600;
            font-size: 1.2rem;
        }
        /* Real Google Sign-In button container */
        #g_id_onload {
            display: inline-block;
        }
        .g_id_signin {
            display: inline-block;
        }
        .main-panel {
            background: #eaf1fc;
            border-radius: 2.5rem;
            padding: 2rem 2rem 2.2rem;
            box-shadow: inset 0 1px 5px white, 0 12px 28px -12px #1e3a60;
            margin: 1.8rem 0 1rem;
        }
        .greeting {
            font-size: 1.6rem;
            font-weight: 600;
            color: #04224e;
            margin-bottom: 0.5rem;
        }
        .user-highlight {
            background: #ffffffc4;
            border-radius: 60px;
            padding: 0.8rem 1.6rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin: 1rem 0 1.8rem;
        }
        .user-avatar {
            width: 56px;
            height: 56px;
            background: #3670b3;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.2rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .user-email {
            font-size: 1.3rem;
            color: #1d3f6e;
            word-break: break-all;
            background: white;
            padding: 0.4rem 1.4rem;
            border-radius: 60px;
        }
        .sms-demo {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            align-items: center;
            justify-content: space-between;
            background: #ffffffb5;
            border-radius: 2rem;
            padding: 1.6rem 2rem;
            margin-top: 1.2rem;
        }
        .sms-address-demo {
            font-size: 1.7rem;
            font-weight: 600;
            color: #10386b;
            background: #dde9ff;
            padding: 0.3rem 1.4rem;
            border-radius: 60px;
        }
        .send-sms-btn {
            background: #254f85;
            color: white;
            border: none;
            padding: 0.9rem 2.4rem;
            border-radius: 60px;
            font-size: 1.3rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 12px 20px -10px #0c2442;
            transition: 0.1s;
        }
        .send-sms-btn:hover {
            background: #2f62a3;
        }
        .footnote {
            background: #dce6f5;
            border-radius: 30px;
            padding: 1rem 2rem;
            color: #142f51;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: space-between;
            margin-top: 2.2rem;
        }
        .status-badge {
            background: #8a9fc7;
            color: white;
            padding: 0.2rem 1.2rem;
            border-radius: 60px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .status-badge.signed-in {
            background: #119955;
        }
        .setup-note {
            background: #fff3d7;
            border-left: 5px solid #f9a826;
            padding: 1rem 1.5rem;
            border-radius: 40px;
            margin-top: 1.5rem;
            font-size: 0.95rem;
        }
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="header-row">
            <div class="logo-area">
                <span class="sms-badge">✉️ SMS@email.com</span>
            </div>
            <!-- REAL Google Sign-In button will be rendered here by the library -->
            <div id="google-signin-button"></div>
            <!-- This appears after sign-in, initially hidden -->
            <div id="user-profile-header" class="hidden" style="display: flex; align-items: center; gap: 0.8rem;">
                <span id="user-name-header"></span>
                <button id="signout-button" style="background: transparent; border: 1px solid #1a3f6e; padding: 0.4rem 1.2rem; border-radius: 40px; cursor: pointer;">Sign out</button>
            </div>
        </div>

        <div class="main-panel">
            <div class="greeting" id="greetingMsg">👋 Sign in with Google to send SMS via email.com</div>
            
            <!-- Logged out content (visible by default) -->
            <div id="loggedOutContent">
                <div class="user-highlight" style="justify-content: center; opacity:0.7;">
                    <span style="font-size:1.3rem;">🔒 You're not signed in</span>
                </div>
            </div>

            <!-- Logged in content (hidden by default) -->
            <div id="loggedInContent" style="display: none;">
                <div class="user-highlight">
                    <div class="user-avatar" id="userAvatar"></div>
                    <span class="user-email" id="userEmailDisplay"></span>
                </div>
                <div class="sms-demo">
                    <span class="sms-address-demo" id="demoSmsAddress">2125551234@email.com</span>
                    <button class="send-sms-btn" id="demoSendSmsBtn">
                        💬 Send SMS via email
                    </button>
                </div>
                <p style="margin-top:1rem; color:#1d3f66;">✅ You're logged in with your Google account.</p>
            </div>
        </div>

        <div class="footnote">
            <span>🔐 <strong>Real Google Sign-In</strong> — works with actual Google accounts</span>
            <span class="status-badge" id="liveStatus">⏳ signed out</span>
        </div>
        
        <!-- IMPORTANT: Setup instructions for the user -->
        <div class="setup-note" id="setupInstructions">
            <strong>⚙️ One-time setup required:</strong> Replace <code>YOUR_GOOGLE_CLIENT_ID</code> in the code with your actual Client ID from <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a> [<a href="https://developers.google.com/identity/gsi/web/guides/get-google-api-clientid" target="_blank">citation:6</a>].<br>
            <small>Create a project, enable OAuth consent screen, create a "Web application" client, and add your domain to Authorized JavaScript origins.</small>
        </div>
    </div>

    <script>
        // *********************************************************************
        // REAL Google Sign-In implementation using Google Identity Services
        // *********************************************************************
        
        // !!! YOU MUST REPLACE THIS WITH YOUR ACTUAL CLIENT ID !!!
        // Get it from: https://console.cloud.google.com/apis/credentials
        const YOUR_CLIENT_ID = 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com';
        
        // For testing purposes only - this shows how to detect if client ID is still placeholder
        const isClientIdPlaceholder = YOUR_CLIENT_ID === 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com';
        
        // DOM elements
        const loggedOutDiv = document.getElementById('loggedOutContent');
        const loggedInDiv = document.getElementById('loggedInContent');
        const greetingMsg = document.getElementById('greetingMsg');
        const liveStatus = document.getElementById('liveStatus');
        const userEmailDisplay = document.getElementById('userEmailDisplay');
        const userAvatar = document.getElementById('userAvatar');
        const demoSmsAddress = document.getElementById('demoSmsAddress');
        const demoSendSmsBtn = document.getElementById('demoSendSmsBtn');
        const userProfileHeader = document.getElementById('user-profile-header');
        const userNameHeader = document.getElementById('user-name-header');
        const signoutButton = document.getElementById('signout-button');
        const googleButtonContainer = document.getElementById('google-signin-button');
        const setupInstructions = document.getElementById('setupInstructions');
        
        // Sample phone number for SMS demo
        const SAMPLE_PHONE = '2125551234';
        
        // Initialize user state
        let currentUser = null;
        
        // Update UI based on login state
        function updateUI(user) {
            if (user) {
                // User is signed in
                loggedOutDiv.style.display = 'none';
                loggedInDiv.style.display = 'block';
                googleButtonContainer.style.display = 'none';
                userProfileHeader.classList.remove('hidden');
                
                // Get user info from the GoogleUser object
                const profile = user.getBasicProfile();
                const name = profile.getName();
                const email = profile.getEmail();
                const imageUrl = profile.getImageUrl();
                const firstName = name.split(' ')[0] || 'User';
                
                greetingMsg.textContent = `👋 Welcome back, ${firstName}!`;
                liveStatus.textContent = '✅ signed in';
                liveStatus.classList.add('signed-in');
                
                // Update profile elements
                userNameHeader.textContent = name;
                userEmailDisplay.textContent = email;
                
                // Set avatar (use first letter if no image)
                if (imageUrl) {
                    userAvatar.innerHTML = `<img src="${imageUrl}" style="width:56px; height:56px; border-radius:50%;" alt="avatar">`;
                } else {
                    userAvatar.textContent = name.charAt(0).toUpperCase();
                }
                
                // SMS demo address (could be customized per user, but using sample for demo)
                demoSmsAddress.textContent = `${SAMPLE_PHONE}@email.com`;
                
                // Hide setup instructions if client ID is set
                if (!isClientIdPlaceholder) {
                    setupInstructions.style.display = 'none';
                }
            } else {
                // User is signed out
                loggedOutDiv.style.display = 'block';
                loggedInDiv.style.display = 'none';
                googleButtonContainer.style.display = 'block';
                userProfileHeader.classList.add('hidden');
                
                greetingMsg.textContent = '👋 Sign in with Google to send SMS via email.com';
                liveStatus.textContent = '⏳ signed out';
                liveStatus.classList.remove('signed-in');
                
                // Show setup instructions if needed
                if (isClientIdPlaceholder) {
                    setupInstructions.style.display = 'block';
                }
            }
        }
        
        // Send SMS demo (only works when logged in)
        function sendSmsDemo() {
            if (!currentUser) {
                alert('🔒 Please sign in with Google first.');
                return;
            }
            const smsAddress = `${SAMPLE_PHONE}@email.com`;
            window.location.href = 'mailto:' + encodeURIComponent(smsAddress) + '?subject=SMS%20message&body=';
        }
        
        // Attach SMS button listener
        demoSendSmsBtn.addEventListener('click', sendSmsDemo);
        
        // Sign out function
        function handleSignOut() {
            if (google.accounts && google.accounts.id) {
                google.accounts.id.disableAutoSelect();
                // Revoke access and sign out
                if (currentUser) {
                    const authInstance = gapi && gapi.auth2 ? gapi.auth2.getAuthInstance() : null;
                    if (authInstance) {
                        authInstance.signOut().then(() => {
                            authInstance.disconnect();
                            currentUser = null;
                            updateUI(null);
                        });
                    } else {
                        // Fallback: just clear local state
                        currentUser = null;
                        updateUI(null);
                        // Reload to reset Google's internal state
                        window.location.reload();
                    }
                } else {
                    currentUser = null;
                    updateUI(null);
                }
            }
        }
        
        signoutButton.addEventListener('click', handleSignOut);
        
        // Initialize Google Sign-In
        window.onload = function() {
            // Check if client ID is placeholder
            if (isClientIdPlaceholder) {
                console.warn('Google Sign-In: Client ID not configured. Using demo mode.');
                setupInstructions.style.display = 'block';
                // Render a demo button that explains setup
                googleButtonContainer.innerHTML = `
                    <button style="background:#f9a826; border:none; padding:0.7rem 2rem; border-radius:40px; font-size:1.2rem; cursor:pointer;" 
                            onclick="alert('⚠️ Setup required: Replace YOUR_GOOGLE_CLIENT_ID in the code with your actual Client ID from Google Cloud Console.\\n\\nGet it here: https://console.cloud.google.com/apis/credentials')">
                        ⚠️ Setup Required
                    </button>
                `;
                return;
            }
            
            // Initialize Google Identity Services
            google.accounts.id.initialize({
                client_id: YOUR_CLIENT_ID,
                callback: handleCredentialResponse,
                auto_select: false,
                cancel_on_tap_outside: true
            });
            
            // Render the Google Sign-In button
            google.accounts.id.renderButton(
                googleButtonContainer,
                { 
                    type: 'standard',
                    theme: 'outline',
                    size: 'large',
                    text: 'signin_with',
                    shape: 'pill',
                    logo_alignment: 'left'
                }
            );
            
            // Also prompt One Tap if needed
            // google.accounts.id.prompt();
            
            // Check if already signed in via gapi (for existing sessions)
            // Load the gapi client for sign-out functionality
            gapi.load('auth2', function() {
                gapi.auth2.init({
                    client_id: YOUR_CLIENT_ID
                }).then(function(auth2) {
                    if (auth2.isSignedIn.get()) {
                        const user = auth2.currentUser.get();
                        currentUser = user;
                        updateUI(user);
                    }
                }).catch(function(error) {
                    console.log('Auth2 init error (normal if first visit):', error);
                });
            });
        };
        
        // Handle the credential response from Google One Tap/button
        function handleCredentialResponse(response) {
            if (response.credential) {
                // Decode the JWT to get user info (client-side)
                // Note: In production, you should verify this on your backend
                const payload = parseJwt(response.credential);
                
                // Create a mock user object since we don't have gapi fully initialized
                // In a real implementation, you'd use gapi.auth2 or validate on backend
                currentUser = {
                    getBasicProfile: function() {
                        return {
                            getName: () => payload.name || 'User',
                            getEmail: () => payload.email || '',
                            getImageUrl: () => payload.picture || ''
                        };
                    }
                };
                
                updateUI(currentUser);
                
                // Also initialize gapi for sign-out capability
                gapi.load('auth2', function() {
                    gapi.auth2.init({
                        client_id: YOUR_CLIENT_ID
                    }).then(function(auth2) {
                        // This helps with sign-out
                    });
                });
            } else {
                console.error('Sign-in failed:', response);
            }
        }
        
        // Helper to parse JWT (for client-side user info display)
        function parseJwt(token) {
            try {
                const base64Url = token.split('.')[1];
                const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
                const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
                    return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
                }).join(''));
                return JSON.parse(jsonPayload);
            } catch (e) {
                return { name: 'User', email: '' };
            }
        }
    </script>
</body>
</html>