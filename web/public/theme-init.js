(function () {
  var preference = 'system'
  try {
    preference = localStorage.getItem('pulsecheck.theme') || 'system'
  } catch {
    preference = 'system'
  }
  var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
  var theme = preference === 'dark' || (preference === 'system' && prefersDark) ? 'dark' : 'light'
  document.documentElement.setAttribute('data-theme', theme)
})()
