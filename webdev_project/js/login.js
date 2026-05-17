document.getElementById("loginForm").addEventListener("submit", async (event) => {
  event.preventDefault();

  if (!window.appApi) {
    alert("The API client failed to load. Please refresh the page.");
    return;
  }

  const username = document.getElementById("username").value.trim();
  const password = document.getElementById("password").value;

  try {
    const result = await window.appApi.post("login.php", { username, password });
    alert(`Login successful! Welcome ${result.user.username} (${result.user.role})`);
    window.location.href = result.user.role === "admin" ? "admin.html" : "main.html";
  } catch (error) {
    alert(error.message || "Unable to log in.");
  }
});

const passwordCheckbox = document.querySelector(".input-box .box");
const passwordField = document.getElementById("password");

if (passwordCheckbox && passwordField) {
  passwordCheckbox.addEventListener("click", function () {
    passwordField.type = this.checked ? "text" : "password";
  });
}
