document.addEventListener("DOMContentLoaded", async () => {
  if (!window.appApi) {
    alert("The API client failed to load. Please refresh the page.");
    return;
  }

  const welcomeEl = document.getElementById("welcome");
  const addItemForm = document.getElementById("addItemForm");
  const userList = document.getElementById("userList");
  const itemList = document.getElementById("itemList");
  const profileLink = document.getElementById("profileLink");
  const profilePanel = document.getElementById("profilePanel");
  const overlay = document.getElementById("overlay");
  const profilePic = document.getElementById("profilePic");
  const profileName = document.getElementById("profileName");
  const profileUpload = document.getElementById("profileUpload");
  const fileName = document.getElementById("fileName");
  const saveBtn = document.getElementById("saveProfilePic");
  const logoutBtn = document.getElementById("logoutBtn");
  const logoutModal = document.getElementById("logoutModal");
  const cancelLogoutBtn = document.getElementById("cancelLogout");
  const confirmLogoutBtn = document.getElementById("confirmLogout");

  let currentUser;
  let products = [];

  try {
    const session = await window.appApi.get("session.php");
    if (!session.authenticated || session.user.role !== "admin") {
      alert("Access denied. Only administrators can view this page.");
      window.location.href = "index.html";
      return;
    }

    currentUser = session.user;
  } catch (error) {
    alert(error.message);
    window.location.href = "index.html";
    return;
  }

  if (welcomeEl) {
    welcomeEl.textContent = `Welcome Admin: ${currentUser.username}`;
  }

  if (profileName) {
    profileName.textContent = `${currentUser.username} (${currentUser.role})`;
  }

  if (profilePic) {
    profilePic.src = currentUser.profile_image || "https://via.placeholder.com/120?text=Profile";
  }

  if (profileLink && profilePanel && overlay) {
    profileLink.addEventListener("click", (event) => {
      event.preventDefault();
      profilePanel.classList.toggle("active");
      overlay.classList.toggle("active");
    });

    overlay.addEventListener("click", () => {
      profilePanel.classList.remove("active");
      overlay.classList.remove("active");
    });
  }

  if (logoutBtn) {
    const toggleLogoutModal = (show) => {
      if (!logoutModal) {
        return;
      }

      logoutModal.classList.toggle("show", show);
      logoutModal.setAttribute("aria-hidden", String(!show));
    };

    logoutBtn.addEventListener("click", () => {
      toggleLogoutModal(true);
    });

    if (cancelLogoutBtn) {
      cancelLogoutBtn.addEventListener("click", () => {
        toggleLogoutModal(false);
      });
    }

    if (logoutModal) {
      logoutModal.addEventListener("click", (event) => {
        if (event.target === logoutModal) {
          toggleLogoutModal(false);
        }
      });
    }

    if (confirmLogoutBtn) {
      confirmLogoutBtn.addEventListener("click", async () => {
        try {
          await window.appApi.post("logout.php");
          toggleLogoutModal(false);
          window.location.href = "index.html";
        } catch (error) {
          alert(error.message || "Logout failed. Please try again.");
        }
      });
    }

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && logoutModal?.classList.contains("show")) {
        toggleLogoutModal(false);
      }
    });
  }

  function escapeHtml(value) {
    return String(value).replace(/[&<>"'`]/g, (char) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
      "`": "&#96;"
    }[char]));
  }

  function resizeImage(file, maxWidth = 300, maxHeight = 300, callback) {
    const reader = new FileReader();
    reader.onload = (event) => {
      const img = new Image();
      img.onload = () => {
        const canvas = document.createElement("canvas");
        let width = img.width;
        let height = img.height;

        if (width > height && width > maxWidth) {
          height *= maxWidth / width;
          width = maxWidth;
        } else if (height > maxHeight) {
          width *= maxHeight / height;
          height = maxHeight;
        }

        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext("2d");
        ctx.drawImage(img, 0, 0, width, height);
        callback(canvas.toDataURL("image/jpeg", 0.8));
      };
      img.src = event.target.result;
    };
    reader.readAsDataURL(file);
  }

  if (profileUpload && profilePic) {
    profileUpload.addEventListener("change", (event) => {
      const file = event.target.files[0];
      if (!file || !file.type.startsWith("image/")) {
        if (fileName) {
          fileName.textContent = "No file selected";
        }
        alert("Please select an image file.");
        return;
      }

      if (fileName) {
        fileName.textContent = file.name;
      }

      resizeImage(file, 800, 800, (resizedDataUrl) => {
        profilePic.src = resizedDataUrl;
      });
    });
  }

  if (saveBtn && profilePic) {
    saveBtn.addEventListener("click", async () => {
      try {
        const result = await window.appApi.post("profile.php", {
          profile_image: profilePic.src
        });
        alert(result.message);
      } catch (error) {
        alert(error.message);
      }
    });
  }

  async function loadUsers() {
    const result = await window.appApi.get("admin_users.php");
    userList.innerHTML = "";

    result.users.forEach((user) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${escapeHtml(user.username)}</td>
        <td>Hidden</td>
        <td>${escapeHtml(user.role)}</td>
        <td>${user.role !== "admin" ? `<button data-user-id="${user.id}" class="delete-user-btn">Delete</button>` : ""}</td>
      `;
      userList.appendChild(tr);
    });

    document.querySelectorAll(".delete-user-btn").forEach((button) => {
      button.addEventListener("click", async () => {
        const userId = Number(button.dataset.userId);
        if (!confirm("Are you sure you want to delete this user?")) {
          return;
        }

        try {
          const response = await window.appApi.post("admin_users.php", {
            action: "delete",
            user_id: userId
          });
          alert(response.message);
          await loadUsers();
        } catch (error) {
          alert(error.message);
        }
      });
    });
  }

  async function loadProducts() {
    const result = await window.appApi.get("products.php");
    products = result.products;
    itemList.innerHTML = "";

    products.forEach((item) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td><img src="${item.image}" style="width:60px;height:60px;object-fit:cover" alt="${escapeHtml(item.name)}"></td>
        <td>${escapeHtml(item.name)}</td>
        <td>PHP ${Number(item.price).toFixed(2)}</td>
        <td>${escapeHtml(item.category)}</td>
        <td>${Number(item.sold_count) || 0}</td>
        <td>
          <button data-product-id="${item.id}" class="edit-item-btn">Edit</button>
          <button data-product-id="${item.id}" class="delete-item-btn">Delete</button>
        </td>
      `;
      itemList.appendChild(tr);
    });

    document.querySelectorAll(".delete-item-btn").forEach((button) => {
      button.addEventListener("click", async () => {
        const productId = Number(button.dataset.productId);
        if (!confirm("Are you sure you want to delete this product?")) {
          return;
        }

        try {
          const response = await window.appApi.post("product_action.php", {
            action: "delete",
            product_id: productId
          });
          alert(response.message);
          await loadProducts();
        } catch (error) {
          alert(error.message);
        }
      });
    });

    document.querySelectorAll(".edit-item-btn").forEach((button) => {
      button.addEventListener("click", async () => {
        const productId = Number(button.dataset.productId);
        const item = products.find((product) => product.id === productId);
        if (!item) {
          return;
        }

        const newName = prompt("Item Name:", item.name);
        if (newName === null) {
          return;
        }

        const newPrice = prompt("Price:", item.price);
        if (newPrice === null) {
          return;
        }

        const categoryOptions = ["T-Shirt", "Shorts", "Shoes", "Accessories", "Others"];
        const newCategory = prompt(`Category (${categoryOptions.join(", ")}):`, item.category);
        if (newCategory === null) {
          return;
        }

        const fileInput = document.createElement("input");
        fileInput.type = "file";
        fileInput.accept = "image/*";
        fileInput.click();
        fileInput.onchange = () => {
          const file = fileInput.files[0];
          if (!file) {
            alert("Please select an image.");
            return;
          }

          resizeImage(file, 300, 300, async (resizedDataUrl) => {
            try {
              const response = await window.appApi.post("product_action.php", {
                action: "update",
                product_id: productId,
                name: newName.trim(),
                price: Number(newPrice),
                category: newCategory.trim(),
                image: resizedDataUrl
              });
              alert(response.message);
              await loadProducts();
            } catch (error) {
              alert(error.message);
            }
          });
        };
      });
    });
  }

  addItemForm.addEventListener("submit", (e) => {
    e.preventDefault();
    const name = document.getElementById("itemName").value.trim();
    const price = Number(document.getElementById("itemPrice").value);
    const category = document.getElementById("itemCategory").value.trim();
    const file = document.getElementById("itemImage").files[0];

    if (!file) {
      alert("Please select an image.");
      return;
    }

    resizeImage(file, 300, 300, async (resizedDataUrl) => {
      try {
        const response = await window.appApi.post("products.php", {
          name,
          price,
          category,
          image: resizedDataUrl
        });
        alert(response.message);
        addItemForm.reset();
        await loadProducts();
      } catch (error) {
        alert(error.message);
      }
    });
  });

  await loadUsers();
  await loadProducts();
});
