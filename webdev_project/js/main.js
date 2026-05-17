document.addEventListener("DOMContentLoaded", async () => {
  if (!window.appApi) {
    alert("The API client failed to load. Please refresh the page.");
    return;
  }

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
  const searchBox = document.getElementById("searchBox");

  let currentUser;
  let allProducts = [];

  try {
    const session = await window.appApi.get("session.php");
    if (!session.authenticated) {
      alert("You must be logged in to view this page.");
      window.location.href = "index.html";
      return;
    }

    currentUser = session.user;
  } catch (error) {
    alert(error.message);
    window.location.href = "index.html";
    return;
  }

  if (currentUser.role === "admin") {
    const cartNav = document.querySelector(".cart-link, #cartLink, a[href='cart.html']");
    if (cartNav) {
      cartNav.style.display = "none";
    }
  }

  if (profileName) {
    profileName.textContent = `${currentUser.username} (${currentUser.role})`;
  }

  if (profilePic) {
    profilePic.src = currentUser.profile_image || "https://via.placeholder.com/120?text=Profile";
  }

  const adminLinkDiv = document.querySelector(".admin-link");
  if (adminLinkDiv) {
    adminLinkDiv.style.display = currentUser.role === "admin" ? "block" : "none";
  }

  if (profileLink && profilePanel && overlay) {
    profileLink.addEventListener("click", (e) => {
      e.preventDefault();
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

  if (profileUpload && profilePic) {
    profileUpload.addEventListener("change", (e) => {
      const file = e.target.files[0];
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

  const dropdownLink = document.querySelector(".nav-links .dropdown > a");
  const dropdownLi = dropdownLink?.parentElement;
  if (dropdownLink && dropdownLi) {
    dropdownLink.addEventListener("click", (e) => {
      e.preventDefault();
      dropdownLi.classList.toggle("active");
    });

    document.addEventListener("click", (e) => {
      if (!dropdownLi.contains(e.target)) {
        dropdownLi.classList.remove("active");
      }
    });
  }

  const hamburger = document.querySelector(".hamburger");
  const navLinks = document.querySelector(".nav-links");
  if (hamburger && navLinks) {
    hamburger.addEventListener("click", () => navLinks.classList.toggle("active"));
  }

  const slides = document.querySelectorAll(".slide");
  const btns = document.querySelectorAll(".btn");
  let currentSlide = 0;

  function manualNav(index) {
    slides.forEach((slide) => slide.classList.remove("active"));
    btns.forEach((btn) => btn.classList.remove("active"));
    slides[index].classList.add("active");
    btns[index].classList.add("active");
    currentSlide = index;
  }

  btns.forEach((btn, index) => {
    btn.addEventListener("click", () => manualNav(index));
  });

  if (slides.length > 0) {
    setInterval(() => {
      const nextSlide = currentSlide + 1 >= slides.length ? 0 : currentSlide + 1;
      manualNav(nextSlide);
    }, 3000);
  }

  window.addEventListener("scroll", () => {
    const navbar = document.querySelector(".navbar");
    if (navbar) {
      navbar.classList.toggle("scrolled", window.scrollY > 50);
    }
  });

  async function fetchProducts() {
    const result = await window.appApi.get("products.php");
    allProducts = result.products;

    renderProducts();
  }

  function renderProducts(filterCategory = null, searchText = "") {
    const container = document.querySelector(".product-container");
    if (!container) {
      return;
    }

    container.innerHTML = "";

    let filteredProducts = [...allProducts];
    if (filterCategory) {
      filteredProducts = filteredProducts.filter((item) => item.category.toLowerCase() === filterCategory.toLowerCase());
    }

    if (searchText.trim() !== "") {
      filteredProducts = filteredProducts.filter((item) =>
        item.name.toLowerCase().includes(searchText.toLowerCase())
      );
    }

    if (filteredProducts.length === 0) {
      container.innerHTML = '<p style="text-align:center;">No matching products found.</p>';
      return;
    }

    filteredProducts.forEach((item) => {
      const card = document.createElement("div");
      card.classList.add("product-card");

      const img = document.createElement("img");
      img.src = item.image;
      img.alt = item.name;

      const name = document.createElement("h3");
      name.textContent = item.name;

      const category = document.createElement("p");
      category.textContent = `Category: ${item.category}`;
      category.classList.add("category");

      const price = document.createElement("p");
      price.textContent = `PHP ${Number(item.price).toFixed(2)}`;
      price.classList.add("price");

      if (currentUser.role === "admin") {
        const salesInfo = document.createElement("p");
        salesInfo.textContent = `Sold: ${item.sold_count}`;
        salesInfo.classList.add("sales-info");
        card.append(img, name, category, price, salesInfo);
      } else {
        const addToCartBtn = document.createElement("button");
        addToCartBtn.textContent = "Add to Cart";
        addToCartBtn.addEventListener("click", () => openQuantityPopup(item, "cart"));

        const buyBtn = document.createElement("button");
        buyBtn.textContent = "Buy Now";
        buyBtn.style.backgroundColor = "#4CAF50";
        buyBtn.addEventListener("click", () => openQuantityPopup(item, "buy"));

        const btnContainer = document.createElement("div");
        btnContainer.classList.add("btn-container");
        btnContainer.style.display = "flex";
        btnContainer.style.justifyContent = "center";
        btnContainer.style.gap = "10px";
        btnContainer.append(addToCartBtn, buyBtn);

        card.append(img, name, category, price, btnContainer);
      }

      container.appendChild(card);
    });
  }

  function openQuantityPopup(item, mode) {
    const popup = document.getElementById("quantityPopup");
    const img = popup.querySelector("img");
    const qtyInput = popup.querySelector("#qtyInput");
    const cancelBtn = popup.querySelector(".cancel");
    const proceedBtn = popup.querySelector(".proceed");
    const decreaseBtn = popup.querySelector("#decreaseQty");
    const increaseBtn = popup.querySelector("#increaseQty");

    img.src = item.image;
    qtyInput.value = 1;
    popup.style.display = "flex";

    decreaseBtn.onclick = () => {
      if (Number(qtyInput.value) > 1) {
        qtyInput.value = Number(qtyInput.value) - 1;
      }
    };

    increaseBtn.onclick = () => {
      qtyInput.value = Number(qtyInput.value) + 1;
    };

    cancelBtn.onclick = () => {
      popup.style.display = "none";
    };

    proceedBtn.onclick = async () => {
      const quantity = Math.max(1, parseInt(qtyInput.value, 10) || 1);

      try {
        if (mode === "cart") {
          const result = await window.appApi.post("cart.php", {
            product_id: item.id,
            quantity
          });
          alert(result.message);
        } else {
          const result = await window.appApi.post("purchases.php", {
            product_id: item.id,
            quantity
          });
          alert(`${result.message} (${item.name} x${quantity})`);
          await loadPurchaseHistory();
          await fetchProducts();
        }
      } catch (error) {
        alert(error.message);
      }

      popup.style.display = "none";
    };
  }

  if (searchBox) {
    searchBox.addEventListener("input", () => {
      const activeCategoryLink = document.querySelector(".dropdown-menu a.active");
      const category = activeCategoryLink?.dataset.category || null;
      renderProducts(category, searchBox.value);
    });
  }

  document.querySelectorAll(".dropdown-menu a").forEach((link) => {
    link.addEventListener("click", (e) => {
      e.preventDefault();
      document.querySelectorAll(".dropdown-menu a").forEach((menuLink) => menuLink.classList.remove("active"));
      link.classList.add("active");
      const category = link.dataset.category;
      renderProducts(category, searchBox?.value || "");
    });
  });

  async function loadPurchaseHistory() {
    const purchaseList = document.getElementById("purchaseList");
    if (!purchaseList || currentUser.role === "admin") {
      return;
    }

    try {
      const result = await window.appApi.get("purchases.php");
      purchaseList.innerHTML = "";

      if (result.purchases.length === 0) {
        purchaseList.innerHTML = '<p class="no-purchases">No purchases yet.</p>';
        return;
      }

      result.purchases.forEach((purchase) => {
        const div = document.createElement("div");
        div.classList.add("purchase-item");
        div.innerHTML = `
          <img src="${purchase.image}" alt="${purchase.name}" />
          <div class="purchase-info">
            <h4>${purchase.name}</h4>
            <p>PHP ${Number(purchase.price).toFixed(2)} x ${purchase.quantity} = PHP ${Number(purchase.total).toFixed(2)}</p>
          </div>
          <button class="delete-purchase-btn">-</button>
        `;

        div.querySelector(".delete-purchase-btn").addEventListener("click", async () => {
          if (!confirm(`Delete "${purchase.name}" from your purchases?`)) {
            return;
          }

          try {
            await window.appApi.post("purchase_action.php", {
              action: "delete",
              purchase_id: purchase.id
            });
            await loadPurchaseHistory();
            await fetchProducts();
          } catch (error) {
            alert(error.message);
          }
        });

        purchaseList.appendChild(div);
      });
    } catch (error) {
      purchaseList.innerHTML = `<p class="no-purchases">${error.message}</p>`;
    }
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

  await fetchProducts();
  await loadPurchaseHistory();
});
