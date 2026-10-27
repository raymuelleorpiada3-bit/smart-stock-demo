function togglePasswordField(fieldId) {
  const input = document.getElementById(fieldId);
  const button = document.querySelector(`button[onclick*="${fieldId}"]`);
  const isPassword = input.type === "password";
  
  input.type = isPassword ? "text" : "password";
  
  // SVG icons
  const openEyeSVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
  const closedEyeSVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
  
  button.innerHTML = isPassword ? closedEyeSVG : openEyeSVG;
  input.focus();
}

function showAuthForm(form) {
  const loginForm = document.getElementById("login-form");
  const registerForm = document.getElementById("register-form");
  const authEntry = document.getElementById("auth-entry");

  authEntry.style.display = "none";
  loginForm.style.display = form === "login" ? "block" : "none";
  registerForm.style.display = form === "register" ? "block" : "none";
}

function showEntry() {
  const loginForm = document.getElementById("login-form");
  const registerForm = document.getElementById("register-form");
  const authEntry = document.getElementById("auth-entry");

  authEntry.style.display = "block";
  loginForm.style.display = "none";
  registerForm.style.display = "none";
}

function toggleForms() {
  const loginForm = document.getElementById("login-form");
  const activeForm = loginForm.style.display === "block" ? "login" : "register";
  showAuthForm(activeForm === "login" ? "register" : "login");
}

function toggleStoreInput() {
  const storeOption = document.querySelector(
    'input[name="store_option"]:checked'
  ).value;
  const existingStoreGroup = document.getElementById("existing-store-group");
  const newStoreGroup = document.getElementById("new-store-group");
  const storeSelect = document.getElementById("store-select");
  const customStoreName = document.getElementById("custom-store-name");

  if (storeOption === "existing") {
    existingStoreGroup.style.display = "block";
    newStoreGroup.style.display = "none";
    storeSelect.required = true;
    customStoreName.required = false;
  } else {
    existingStoreGroup.style.display = "none";
    newStoreGroup.style.display = "block";
    storeSelect.required = false;
    customStoreName.required = true;
  }
}

// Load stores from database
function loadStores() {
  fetch("php/locations/get_stores.php")
    .then((response) => response.json())
    .then((data) => {
      const storeSelect = document.getElementById("store-select");
      data.forEach((store) => {
        const option = document.createElement("option");
        option.value = store.store_id;
        option.textContent = store.store_name;
        storeSelect.appendChild(option);
      });
    })
    .catch((error) => console.error("Error loading stores:", error));
}

// Load provinces
function loadProvinces() {
  fetch("php/locations/get_provinces.php")
    .then((response) => response.json())
    .then((data) => {
      const provinceSelect = document.getElementById("province");
      data.forEach((province) => {
        const option = document.createElement("option");
        option.value = province.prov_id;
        option.textContent = province.prov_name;
        provinceSelect.appendChild(option);
      });
    })
    .catch((error) => console.error("Error loading provinces:", error));
}

// Load municipalities based on province
document.addEventListener("DOMContentLoaded", function () {
  const provinceSelect = document.getElementById("province");
  const municipalitySelect = document.getElementById("municipality");
  const barangaySelect = document.getElementById("barangay");

  provinceSelect.addEventListener("change", function () {
    const provinceId = this.value;
    municipalitySelect.innerHTML =
      '<option value="">-- Select Municipality --</option>';
    barangaySelect.innerHTML = '<option value="">-- Select Barangay --</option>';
    barangaySelect.disabled = true;

    if (provinceId) {
      fetch(`php/locations/get_municipalities.php?province_id=${provinceId}`)
        .then((response) => response.json())
        .then((data) => {
          data.forEach((municipality) => {
            const option = document.createElement("option");
            option.value = municipality.mun_id;
            option.textContent = municipality.mun_name;
            municipalitySelect.appendChild(option);
          });
          municipalitySelect.disabled = false;
        })
        .catch((error) =>
          console.error("Error loading municipalities:", error)
        );
    } else {
      municipalitySelect.disabled = true;
    }
  });

  municipalitySelect.addEventListener("change", function () {
    const municipalityId = this.value;
    barangaySelect.innerHTML =
      '<option value="">-- Select Barangay --</option>';

    if (municipalityId) {
      fetch(`php/locations/get_barangays.php?municipality_id=${municipalityId}`)
        .then((response) => response.json())
        .then((data) => {
          data.forEach((barangay) => {
            const option = document.createElement("option");
            option.value = barangay.brgy_id;
            option.textContent = barangay.brgy_name;
            barangaySelect.appendChild(option);
          });
          barangaySelect.disabled = false;
        })
        .catch((error) => console.error("Error loading barangays:", error));
    } else {
      barangaySelect.disabled = true;
    }
  });
});

// Load stores when page loads
window.addEventListener("load", loadStores);
window.addEventListener("load", loadProvinces);

// Display messages if they exist in URL
const params = new URLSearchParams(window.location.search);
const message = params.get("message");
const error = params.get("error");

if (message) {
  const loginMsg = document.getElementById("login-message");
  loginMsg.textContent = message;
  loginMsg.classList.add("success");
  showAuthForm("login");
} else if (error) {
  const form =
    params.get("form") === "register" ? "register" : "login";
  const msgDiv =
    form === "register"
      ? document.getElementById("register-message")
      : document.getElementById("login-message");
  msgDiv.textContent = error;
  msgDiv.classList.add("error");
  showAuthForm(form);
} else {
  showAuthForm("login");
}
