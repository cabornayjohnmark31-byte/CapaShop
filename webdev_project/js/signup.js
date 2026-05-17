document.getElementById("signupForm").addEventListener("submit", async (event) => {
  event.preventDefault();

  if (!window.appApi) {
    alert("The API client failed to load. Please refresh the page.");
    return;
  }

  const username = document.getElementById("username").value.trim();
  const password = document.getElementById("password").value;
  const repassword = document.getElementById("repassword").value;

  if (password !== repassword) {
    alert("Passwords do not match!");
    return;
  }

  try {
    const result = await window.appApi.post("signup.php", { username, password });
    alert(result.message);
    window.location.href = "index.html";
  } catch (error) {
    alert(error.message || "Unable to create your account.");
  }
});
