/*
  Requirement: Populate the "Weekly Course Breakdown" list page.

  Instructions:
  1. This file is already linked to `list.html` via:
         <script src="list.js" defer></script>

  2. In `list.html`, the <section id="week-list-section"> is the container
     that this script populates.

  3. Implement the TODOs below.
*/

// --- Element Selections ---
const weekListSection = document.getElementById('week-list-section');
// --- Functions ---

function createWeekArticle(week) {
  const article = document.createElement('article');

  article.innerHTML = `
    <h2>${week.title}</h2>
    <p>Starts on: ${week.start_date}</p>
    <p>${week.description}</p>
    <a href="details.html?id=${week.id}">View Details & Discussion</a>
  `;

  return article;
}

async function loadWeeks() {
  const response = await fetch('./api/index.php');
  const result = await response.json();

  if (!result.success) return;

  const weeks = result.data;

  weekListSection.innerHTML = '';

  weeks.forEach(week => {
    const article = createWeekArticle(week);
    weekListSection.appendChild(article);
  });
}

// --- Initial Page Load ---
loadWeeks();
