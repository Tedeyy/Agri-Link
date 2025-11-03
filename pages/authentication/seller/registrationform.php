<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Registration Form</title>
    <link rel="stylesheet" href="style/registrationform.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="regform">
        <form id="seller-reg-form" action="req.php" method="post" enctype="multipart/form-data">
            <h2>Seller Registration Details</h2>
            First Name<br>
            <input type="text" id="firstname" name="firstname" required>
            <br><br>
            Middle Name<br>
            <input type="text" id="middlename" name="middlename" required>
            <br><br>
            Last Name<br>
            <input type="text" id="lastname" name="lastname" required>
            <br><br>
            Birthdate<br>
            <input type="date" id="bdate" name="bdate" required>
            <br><br>
            Contact Number<br>
            <input type="tel" id="contact" name="contact" inputmode="numeric" pattern="\d{11}" maxlength="11" required>
            <br><br>
            Email Address<br>
            <input type="email" id="email" name="email" required>
            <br><br>
            RSBSA Number<br>
            <input type="text" id="rsbsanum" name="rsbsanum" required>
            <br><br>

            <!-- New address fields -->
            Address<br>
            <input type="text" id="address" name="address" required>
            <br><br>
            Barangay<br>
            <input type="text" id="barangay" name="barangay" required>
            <br><br>
            Municipality<br>
            <input type="text" id="municipality" name="municipality" required>
            <br><br>
            Province<br>
            <input type="text" id="province" name="province" required>
            <br><br>

            Valid ID<br>
            <input type="file" id="valid_id" name="valid_id" accept="image/*" required>
            <br><br>
            Valid ID Number<br>
            <input type="text" id="idnum" name="idnum" required>
            <br><br>
            <br><br>
            <div id="acc">Login Credentials<br>
                Username<br>
                <input type="text" id="username" name="username" required>
                <br><br>
                Password<br>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="password" id="password" name="password" required>
                    <button type="button" id="toggle-password">Show</button>
                </div>
                <br>
                Confirm Password<br>
                <input type="password" id="confirm_password" name="confirm_password" required>
                <div id="password_error" style="color:#c00; font-size:12px; display:none;">Passwords do not match.</div>
                <br>
            </div>
            <br><br>
            <input type="hidden" id="desired_id_filename" name="desired_id_filename" value="">
            <input type="hidden" id="confirmed" name="confirmed" value="0">
            <button type="submit" id="proceed_btn" name="next" value="next">Proceed</button>
            <br>
        </form>
    </div>
    <div id="confirm_modal" style="position:fixed; inset:0; display:none; align-items:center; justify-content:center; background:rgba(0,0,0,0.4);">
        <div style="background:#fff; max-width:640px; width:90%; padding:20px; border-radius:8px; max-height:80vh; overflow:auto;">
            <h3>Confirm Details</h3>
            <div id="confirm_content" style="margin-top:12px; line-height:1.6;"></div>
            <div style="display:flex; gap:12px; justify-content:flex-end; margin-top:16px;">
                <button type="button" id="edit_btn">Edit</button>
                <button type="button" id="confirm_btn">Confirm</button>
            </div>
        </div>
    </div>
    <script>
    (function(){
        var form = document.getElementById('seller-reg-form');
        var contact = document.getElementById('contact');
        var validId = document.getElementById('valid_id');
        var desiredFile = document.getElementById('desired_id_filename');
        var firstname = document.getElementById('firstname');
        var lastname = document.getElementById('lastname');
        var togglePwd = document.getElementById('toggle-password');
        var pwd = document.getElementById('password');
        var cpwd = document.getElementById('confirm_password');
        var pwdErr = document.getElementById('password_error');
        var confirmed = document.getElementById('confirmed');
        var modal = document.getElementById('confirm_modal');
        var confirmContent = document.getElementById('confirm_content');
        var confirmBtn = document.getElementById('confirm_btn');
        var editBtn = document.getElementById('edit_btn');

        function sanitizeName(s){
            return (s||'').toString().trim().toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'');
        }

        contact.addEventListener('input', function(e){
            var digits = this.value.replace(/\D/g, '');
            if (digits.length > 11) digits = digits.slice(0,11);
            this.value = digits;
        });

        togglePwd.addEventListener('click', function(){
            if (pwd.type === 'password') {
                pwd.type = 'text';
                cpwd.type = 'text';
                this.textContent = 'Hide';
            } else {
                pwd.type = 'password';
                cpwd.type = 'password';
                this.textContent = 'Show';
            }
        });

        function updateDesiredFilename(){
            var f = sanitizeName(firstname.value);
            var l = sanitizeName(lastname.value);
            var base = (f && l) ? (f + '_' + l + '_id') : '';
            var ext = '';
            if (validId.files && validId.files[0] && validId.files[0].name) {
                var name = validId.files[0].name;
                var i = name.lastIndexOf('.');
                if (i > -1) ext = name.slice(i);
            }
            desiredFile.value = base ? (base + ext) : '';
        }

        firstname.addEventListener('input', updateDesiredFilename);
        lastname.addEventListener('input', updateDesiredFilename);
        validId.addEventListener('change', updateDesiredFilename);

        function passwordsMatch(){
            return pwd.value === cpwd.value;
        }

        function buildPreview(){
            var fields = [
                ['First Name', document.getElementById('firstname').value],
                ['Middle Name', document.getElementById('middlename').value],
                ['Last Name', document.getElementById('lastname').value],
                ['Birthdate', document.getElementById('bdate').value],
                ['Contact Number', document.getElementById('contact').value],
                ['Email', document.getElementById('email').value],
                ['RSBSA Number', document.getElementById('rsbsanum').value],
                ['Address', document.getElementById('address').value],
                ['Barangay', document.getElementById('barangay').value],
                ['Municipality', document.getElementById('municipality').value],
                ['Province', document.getElementById('province').value],
                ['Valid ID Filename', desiredFile.value || (validId.files[0] ? validId.files[0].name : '')],
                ['Valid ID Number', document.getElementById('idnum').value],
                ['Username', document.getElementById('username').value]
            ];
            var html = '';
            for (var i=0;i<fields.length;i++){
                html += '<div><strong>' + fields[i][0] + ':</strong><br>' + (fields[i][1] || '') + '</div>';
            }
            confirmContent.innerHTML = html;
        }

        form.addEventListener('submit', function(e){
            if (confirmed.value === '1') return; // allow actual submit
            e.preventDefault();
            if (!passwordsMatch()){
                pwdErr.style.display = 'block';
                cpwd.focus();
                return;
            } else {
                pwdErr.style.display = 'none';
            }
            if (contact.value.length !== 11){
                contact.focus();
                return;
            }
            updateDesiredFilename();
            if (!desiredFile.value){
                updateDesiredFilename();
            }
            if (!form.checkValidity()){
                form.reportValidity();
                return;
            }
            buildPreview();
            modal.style.display = 'flex';
        });

        editBtn.addEventListener('click', function(){
            modal.style.display = 'none';
        });
        confirmBtn.addEventListener('click', function(){
            confirmed.value = '1';
            modal.style.display = 'none';
            form.submit();
        });
    })();
    </script>
</body>
</html>
