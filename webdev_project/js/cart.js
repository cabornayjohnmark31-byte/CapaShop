document.addEventListener("DOMContentLoaded", async () => {
  if (!window.appApi) {
    alert("The API client failed to load. Please refresh the page.");
    return;
  }

  const cartContainer = document.getElementById("cartContainer");

  function escapeHtml(value) {
    return String(value).replace(/[&<>\"'`]/g, (char) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
      "`": "&#96;"
    }[char]));
  }

  try {
    const session = await window.appApi.get("session.php");

    if (!session.authenticated) {
      alert("You must be logged in to view your cart.");
      window.location.href = "index.html";
      return;
    }

    if (session.user.role === "admin") {
      alert("Admins cannot access the cart.");
      window.location.href = "main.html";
      return;
    }
  } catch (error) {
    alert(error.message);
    window.location.href = "index.html";
    return;
  }

  async function loadCart() {
    try {
      const result = await window.appApi.get("cart.php");
      const items = result.items || [];
      cartContainer.innerHTML = "";

      if (items.length === 0) {
        cartContainer.innerHTML = '<p class="empty-cart">Your cart is empty.</p>';
        return;
      }

      items.forEach((item) => {
        const div = document.createElement("div");
        div.classList.add("cart-item");
        div.innerHTML = `
          <img src="${item.image}" alt="${escapeHtml(item.name)}" />
          <div class="cart-item-info">
            <h3>${escapeHtml(item.name)}</h3>
            <p>PHP ${Number(item.price).toFixed(2)}</p>
            <p>Quantity: ${item.quantity}</p>
            <p class="total">Total: PHP ${Number(item.total).toFixed(2)}</p>
          </div>
          <div class="cart-item-buttons">
            <button class="removeBtn">Remove</button>
            <button class="buyBtn">Buy</button>
          </div>
        `;

        div.querySelector(".buyBtn").addEventListener("click", async () => {
          try {
            const response = await window.appApi.post("cart_action.php", {
              action: "buy",
              cart_item_id: item.id
            });
            alert(response.message);
            await loadCart();
          } catch (error) {
            alert(error.message);
          }
        });

        div.querySelector(".removeBtn").addEventListener("click", async () => {
          try {
            const response = await window.appApi.post("cart_action.php", {
              action: "remove",
              cart_item_id: item.id
            });
            alert(response.message);
            await loadCart();
          } catch (error) {
            alert(error.message);
          }
        });

        cartContainer.appendChild(div);
      });
    } catch (error) {
      cartContainer.innerHTML = `<p class="empty-cart">${escapeHtml(error.message)}</p>`;
    }
  }

  await loadCart();
});
