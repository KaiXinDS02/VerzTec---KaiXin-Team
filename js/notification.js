function parseTimestampAsUTC(dateStr) {
  return new Date(dateStr + "Z");
}

function showNotificationPopup(announcement) {
  if (document.querySelector('.custom-alert')) return;

  const alert = document.createElement('div');
  alert.className = 'custom-alert';
  alert.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    background: #fff;
    color: #000;
    border-left: 6px solid #2a4d9c;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.25);
    z-index: 9999;
    border-radius: 10px;
    max-width: 420px;
    width: 90%;
    font-size: 1rem;
    cursor: pointer;
    box-sizing: border-box;
    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
    word-break: break-word;
  `;

  alert.innerHTML = `
    <div style="font-weight: bold; margin-bottom: 0.5rem;">New Announcement:</div>
    <div style="
      font-size: 1.1rem;
      font-weight: 600;
      line-height: 1.4;
      white-space: normal;
      word-wrap: break-word;
      overflow-wrap: break-word;
      word-break: break-word;
      overflow-wrap: anywhere;
      display: block;
      max-width: 100%;
    ">
      ${announcement.title}
    </div>
    <div style="color: gray; font-size: 0.875rem; margin-top: 0.25rem;">Click to view</div>
  `;

  alert.onclick = function () {
    document.getElementById('modalTitle').textContent = announcement.title;
    document.getElementById('modalContent').innerHTML = announcement.context;
    document.getElementById('modalAudience').textContent = announcement.target_audience;
    document.getElementById('modalPriority').textContent = announcement.priority;

    const options = {
      timeZone: 'Asia/Singapore',
      year: 'numeric',
      month: 'short',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hour12: true,
    };

    // Since the PHP backend now formats the timestamp in the user's timezone,
    // we can use it directly instead of converting from UTC
    document.getElementById('modalTimestamp').textContent = announcement.timestamp;

    const modal = new bootstrap.Modal(document.getElementById('announcementModal'));
    modal.show();
    alert.remove();

    // Update last seen only when popup is shown
    localStorage.setItem('lastSeenAnnouncement', announcement.timestamp);
  };

  document.body.appendChild(alert);

  setTimeout(() => {
    if (document.body.contains(alert)) {
      alert.remove();
      // Also update last seen if alert disappears without click
      localStorage.setItem('lastSeenAnnouncement', announcement.timestamp);
    }
  }, 10000);
}

// Initialize last seen timestamp once at page load
(function initializeLastSeen() {
  fetch('/admin/latest_announcement.php')
    .then(res => res.json())
    .then(announcement => {
      if (!announcement) return;
      // Only set if localStorage is empty (first time)
      if (!localStorage.getItem('lastSeenAnnouncement')) {
        localStorage.setItem('lastSeenAnnouncement', announcement.timestamp);
      }
    })
    .catch(() => {
      // Fail silently, or handle error as needed
    });
})();

// Then poll every 10 seconds for new announcements
setInterval(() => {
  fetch('/admin/latest_announcement.php')
    .then(res => res.json())
    .then(announcement => {
      if (!announcement) return;
      const lastSeen = localStorage.getItem('lastSeenAnnouncement') || '';
      if (new Date(announcement.timestamp) > new Date(lastSeen)) {
        showNotificationPopup(announcement);
      }
    });
}, 10000);
