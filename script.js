// Global variables
let currentUser = null
let currentUserType = null

// Page navigation functions
function showPage(pageId) {
  // Hide all pages
  document.querySelectorAll(".page").forEach((page) => {
    page.classList.remove("active")
  })

  // Show selected page
  document.getElementById(pageId).classList.add("active")
}

// Logout functionality
function logout() {
  currentUser = null
  currentUserType = null
  showPage("loginPage")
}

// Client dashboard navigation
function showClientSection(sectionId) {
  // Update sidebar active state
  document.querySelectorAll("#clientDashboard .sidebar-menu li").forEach((li) => {
    li.classList.remove("active")
  })
  event.target.closest("li").classList.add("active")

  // Hide all sections
  document.querySelectorAll("#clientDashboard .content-section").forEach((section) => {
    section.classList.remove("active")
  })

  // Show selected section
  document.getElementById("client" + sectionId.charAt(0).toUpperCase() + sectionId.slice(1)).classList.add("active")
}

// Dentist dashboard navigation
function showDentistSection(sectionId) {
  // Update sidebar active state
  document.querySelectorAll("#dentistDashboard .sidebar-menu li").forEach((li) => {
    li.classList.remove("active")
  })
  event.target.closest("li").classList.add("active")

  // Hide all sections
  document.querySelectorAll("#dentistDashboard .content-section").forEach((section) => {
    section.classList.remove("active")
  })

  // Show selected section
  document.getElementById("dentist" + sectionId.charAt(0).toUpperCase() + sectionId.slice(1)).classList.add("active")
}

// Admin dashboard navigation
function showAdminSection(sectionId) {
  // Update sidebar active state
  document.querySelectorAll("#adminDashboard .sidebar-menu li").forEach((li) => {
    li.classList.remove("active")
  })
  event.target.closest("li").classList.add("active")

  // Hide all sections
  document.querySelectorAll("#adminDashboard .content-section").forEach((section) => {
    section.classList.remove("active")
  })

  // Show selected section
  document.getElementById("admin" + sectionId.charAt(0).toUpperCase() + sectionId.slice(1)).classList.add("active")
}

// Appointment tabs functionality
function showAppointmentTab(tabName) {
  // Update tab buttons
  document.querySelectorAll(".tab-btn").forEach((btn) => {
    btn.classList.remove("active")
  })
  event.target.classList.add("active")

  // Hide all tab contents
  document.querySelectorAll(".tab-content").forEach((content) => {
    content.classList.remove("active")
  })

  // Show selected tab
  document.getElementById(tabName + "Tab").classList.add("active")
}

// Rating stars functionality
document.querySelectorAll(".rating-stars i").forEach((star, index) => {
  star.addEventListener("click", function () {
    const rating = index + 1
    const stars = this.parentElement.querySelectorAll("i")

    stars.forEach((s, i) => {
      if (i < rating) {
        s.classList.remove("far")
        s.classList.add("fas")
        s.classList.add("active")
      } else {
        s.classList.remove("fas", "active")
        s.classList.add("far")
      }
    })
  })

  star.addEventListener("mouseenter", function () {
    const rating = index + 1
    const stars = this.parentElement.querySelectorAll("i")

    stars.forEach((s, i) => {
      if (i < rating) {
        s.style.color = "#ffc107"
      } else {
        s.style.color = "#ddd"
      }
    })
  })
})

document.querySelectorAll(".rating-stars").forEach((container) => {
  container.addEventListener("mouseleave", function () {
    const stars = this.querySelectorAll("i")
    stars.forEach((s) => {
      if (s.classList.contains("active")) {
        s.style.color = "#ffc107"
      } else {
        s.style.color = "#ddd"
      }
    })
  })
})

// Modal functionality
function showComposeModal() {
  document.getElementById("composeModal").classList.add("active")
}

function hideComposeModal() {
  document.getElementById("composeModal").classList.remove("active")
  // Clear form
  document.querySelector("#composeModal form").reset()
}

// Close modal when clicking outside
document.getElementById("composeModal").addEventListener("click", function (e) {
  if (e.target === this) {
    hideComposeModal()
  }
})

// Form submissions
document.querySelector("#clientFeedback form").addEventListener("submit", function (e) {
  e.preventDefault()
  alert("Feedback submitted successfully!")
  this.reset()
  // Reset rating stars
  document.querySelectorAll(".rating-stars i").forEach((star) => {
    star.classList.remove("fas", "active")
    star.classList.add("far")
    star.style.color = "#ddd"
  })
})

document.querySelector("#composeModal form").addEventListener("submit", (e) => {
  e.preventDefault()
  alert("Message sent successfully!")
  hideComposeModal()
})

// Search functionality
document.querySelectorAll(".search-box input").forEach((input) => {
  input.addEventListener("input", function () {
    const searchTerm = this.value.toLowerCase()
    const searchContainer = this.closest(".content-section")

    if (searchContainer) {
      const items = searchContainer.querySelectorAll(".patient-card, .appointment-card, .message-item, tr")

      items.forEach((item) => {
        const text = item.textContent.toLowerCase()
        if (text.includes(searchTerm)) {
          item.style.display = ""
        } else {
          item.style.display = "none"
        }
      })
    }
  })
})

// Filter functionality
document.querySelectorAll(".filter-options select").forEach((select) => {
  select.addEventListener("change", function () {
    const filterValue = this.value.toLowerCase()
    const filterContainer = this.closest(".content-section")

    if (filterContainer) {
      const items = filterContainer.querySelectorAll(".appointment-card, .message-item, .feedback-card, tr")

      items.forEach((item) => {
        if (
          filterValue === "" ||
          filterValue === "all appointments" ||
          filterValue === "all users" ||
          filterValue === "all feedback"
        ) {
          item.style.display = ""
        } else {
          const text = item.textContent.toLowerCase()
          if (text.includes(filterValue)) {
            item.style.display = ""
          } else {
            item.style.display = "none"
          }
        }
      })
    }
  })
})

// Simulate real-time updates
function updateStats() {
  // This would typically fetch data from a server
  // For demo purposes, we'll just update some numbers randomly

  const statCards = document.querySelectorAll(".stat-card h3")
  statCards.forEach((stat) => {
    const currentValue = Number.parseInt(stat.textContent)
    const change = Math.floor(Math.random() * 3) - 1 // -1, 0, or 1
    const newValue = Math.max(0, currentValue + change)
    stat.textContent = newValue
  })
}

// Update stats every 30 seconds (for demo purposes)
setInterval(updateStats, 30000)

// Initialize tooltips and other interactive elements
document.addEventListener("DOMContentLoaded", () => {
  // Add click handlers for appointment actions
  document.querySelectorAll(".appointment-actions .btn").forEach((btn) => {
    btn.addEventListener("click", function (e) {
      e.preventDefault()
      const action = this.textContent.trim().toLowerCase()

      switch (action) {
        case "reschedule":
          alert("Reschedule functionality would open a date picker")
          break
        case "cancel":
          if (confirm("Are you sure you want to cancel this appointment?")) {
            alert("Appointment cancelled")
            this.closest(".appointment-card, .appointment-item").style.display = "none"
          }
          break
        case "edit":
          alert("Edit functionality would open an edit form")
          break
        case "view history":
          alert("Patient history would be displayed")
          break
        case "schedule":
          alert("Schedule appointment functionality would open")
          break
        default:
          alert("Action: " + action)
      }
    })
  })

  // Add click handlers for table action buttons
  document.querySelectorAll("table .btn").forEach((btn) => {
    btn.addEventListener("click", function (e) {
      e.preventDefault()
      const action = this.textContent.trim().toLowerCase()
      const row = this.closest("tr")

      switch (action) {
        case "edit":
          alert("Edit functionality would open an edit form")
          break
        case "cancel":
        case "deactivate":
          if (confirm("Are you sure?")) {
            alert("Action completed")
            row.style.opacity = "0.5"
          }
          break
        default:
          alert("Action: " + action)
      }
    })
  })

  // Add click handlers for message items
  document.querySelectorAll(".message-item").forEach((item) => {
    item.addEventListener("click", function () {
      // Mark as read
      this.classList.remove("unread")
      alert("Message details would be displayed in a modal or new page")
    })
  })

  // Add hover effects for interactive elements
  document.querySelectorAll(".appointment-slot:not(.occupied)").forEach((slot) => {
    slot.addEventListener("click", function () {
      if (confirm("Schedule an appointment for this time slot?")) {
        this.classList.add("occupied")
        this.textContent = "New Patient"
        alert("Appointment scheduled!")
      }
    })
  })
})

// Keyboard shortcuts
document.addEventListener("keydown", (e) => {
  // Escape key to close modals
  if (e.key === "Escape") {
    hideComposeModal()
  }

  // Ctrl/Cmd + N for new message (when in messages section)
  if ((e.ctrlKey || e.metaKey) && e.key === "n") {
    const activeSection = document.querySelector(".content-section.active")
    if (activeSection && activeSection.id.includes("Messages")) {
      e.preventDefault()
      showComposeModal()
    }
  }
})

// Auto-save functionality for forms
document.querySelectorAll('textarea, input[type="text"], input[type="email"]').forEach((input) => {
  input.addEventListener("input", function () {
    // In a real application, this would save to localStorage or send to server
    const formData = {
      field: this.name || this.id,
      value: this.value,
      timestamp: new Date().toISOString(),
    }

    // Save to localStorage for demo
    localStorage.setItem("formDraft_" + (this.name || this.id), JSON.stringify(formData))
  })
})

// Load saved form data on page load
window.addEventListener("load", () => {
  document.querySelectorAll('textarea, input[type="text"], input[type="email"]').forEach((input) => {
    const savedData = localStorage.getItem("formDraft_" + (input.name || input.id))
    if (savedData) {
      const data = JSON.parse(savedData)
      input.value = data.value
    }
  })
})

// Notification system (mock)
function showNotification(message, type = "info") {
  const notification = document.createElement("div")
  notification.className = `notification ${type}`
  notification.textContent = message
  notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === "success" ? "#28a745" : type === "error" ? "#dc3545" : "#17a2b8"};
        color: white;
        padding: 15px 20px;
        border-radius: 6px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        z-index: 1001;
        animation: slideIn 0.3s ease;
    `

  document.body.appendChild(notification)

  setTimeout(() => {
    notification.style.animation = "slideOut 0.3s ease"
    setTimeout(() => {
      document.body.removeChild(notification)
    }, 300)
  }, 3000)
}

// Add CSS for notifications
const notificationStyles = document.createElement("style")
notificationStyles.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`
document.head.appendChild(notificationStyles)

// Demo notifications
setTimeout(() => {
  if (currentUser) {
    showNotification("Welcome to the Dental Clinic Management System!", "success")
  }
}, 1000)
