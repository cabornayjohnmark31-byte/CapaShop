document.addEventListener("DOMContentLoaded", async () => {
  if (!window.appApi) {
    alert("The API client failed to load. Please refresh the page.");
    return;
  }

  const welcomeEl = document.getElementById("welcome");
  const totalSoldEl = document.getElementById("totalSold");
  const totalSalesEl = document.getElementById("totalSales");
  const salesChart = document.getElementById("salesChart");
  const salesItemList = document.getElementById("salesItemList");
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

  function renderSalesChart(items) {
    if (!salesChart) {
      return;
    }

    salesChart.innerHTML = "";

    const maxSold = Math.max(...items.map((item) => Number(item.sold_count) || 0), 0);

    if (items.length === 0) {
      salesChart.innerHTML = '<p class="chart-empty">No products available.</p>';
      return;
    }

    items.forEach((item) => {
      const soldCount = Number(item.sold_count) || 0;
      const barHeight = maxSold === 0 ? 12 : Math.max(12, (soldCount / maxSold) * 180);
      const barItem = document.createElement("div");
      barItem.className = "chart-bar-item";
      barItem.innerHTML = `
        <span class="chart-value">${soldCount}</span>
        <div class="chart-bar" style="height:${barHeight}px"></div>
        <p class="chart-label" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</p>
      `;
      salesChart.appendChild(barItem);
    });
  }

  function resizeImage(file, maxWidth, maxHeight, callback) {
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

  async function loadSales() {
    const result = await window.appApi.get("products.php");

    if (totalSoldEl) {
      totalSoldEl.textContent = result.summary.total_sold;
    }

    if (totalSalesEl) {
      totalSalesEl.textContent = `PHP ${Number(result.summary.total_sales).toFixed(2)}`;
    }

    renderSalesChart(result.products);

    if (!salesItemList) {
      return;
    }

    salesItemList.innerHTML = "";

    result.products.forEach((item) => {
      const soldCount = Number(item.sold_count) || 0;
      const totalAmount = soldCount * Number(item.price);
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td><img src="${item.image}" style="width:60px;height:60px;object-fit:cover" alt="${escapeHtml(item.name)}"></td>
        <td>${escapeHtml(item.name)}</td>
        <td>${escapeHtml(item.category)}</td>
        <td>PHP ${Number(item.price).toFixed(2)}</td>
        <td>${soldCount}</td>
        <td>PHP ${totalAmount.toFixed(2)}</td>
      `;
      salesItemList.appendChild(tr);
    });
  }

  await loadSales();
});
