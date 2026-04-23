/*
  Requirement: Make the "Manage Resources" page interactive.

  Instructions:
  1. Link this file to `admin.html` using:
     <script src="admin.js" defer></script>
  
  2. In `admin.html`, add id="resources-tbody" to the <tbody> element
     inside your resources-table. This id is required by this script.
  
  3. Implement the TODOs below.
*/

// --- Global Data Store ---
let resources = [];
let editingResourceId = null;

// --- Element Selections ---
const resourceForm = document.querySelector('#resource-form');
const resourcesTbody = document.querySelector('#resources-tbody');
const titleInput = document.querySelector('#resource-title');
const descriptionInput = document.querySelector('#resource-description');
const linkInput = document.querySelector('#resource-link');
const submitButton = document.querySelector('#add-resource');

// --- Functions ---

/**
 * Creates a table row for one resource.
 * @param {Object} resource
 * @returns {HTMLTableRowElement}
 */
function createResourceRow(resource) {
  const tr = document.createElement('tr');

  const titleTd = document.createElement('td');
  titleTd.textContent = resource.title;

  const descriptionTd = document.createElement('td');
  descriptionTd.textContent = resource.description || '';

  const linkTd = document.createElement('td');
  const linkAnchor = document.createElement('a');
  linkAnchor.href = resource.link;
  linkAnchor.textContent = resource.link;
  linkAnchor.target = '_blank';
  linkAnchor.rel = 'noopener noreferrer';
  linkTd.appendChild(linkAnchor);

  const actionsTd = document.createElement('td');

  const editButton = document.createElement('button');
  editButton.textContent = 'Edit';
  editButton.className = 'edit-btn';
  editButton.dataset.id = resource.id;

  const deleteButton = document.createElement('button');
  deleteButton.textContent = 'Delete';
  deleteButton.className = 'delete-btn';
  deleteButton.dataset.id = resource.id;

  actionsTd.appendChild(editButton);
  actionsTd.appendChild(deleteButton);

  tr.appendChild(titleTd);
  tr.appendChild(descriptionTd);
  tr.appendChild(linkTd);
  tr.appendChild(actionsTd);

  return tr;
}

/**
 * Renders all resources into the table body.
 */
function renderTable() {
  resourcesTbody.innerHTML = '';

  resources.forEach((resource) => {
    const row = createResourceRow(resource);
    resourcesTbody.appendChild(row);
  });
}

/**
 * Handles add/update resource form submission.
 * @param {SubmitEvent} event
 */
async function handleAddResource(event) {
  event.preventDefault();

  const title = titleInput.value.trim();
  const description = descriptionInput.value.trim();
  const link = linkInput.value.trim();

  if (!title || !link) {
    alert('Title and link are required.');
    return;
  }

  try {
    if (editingResourceId !== null) {
      const response = await fetch('./api/index.php', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          id: editingResourceId,
          title,
          description,
          link
        })
      });

      const result = await response.json();

      if (result.success) {
        resources = resources.map((resource) =>
          Number(resource.id) === Number(editingResourceId)
            ? { ...resource, title, description, link }
            : resource
        );

        renderTable();
        resourceForm.reset();
        editingResourceId = null;
        submitButton.textContent = 'Add Resource';
      } else {
        alert(result.message || 'Failed to update resource.');
      }
    } else {
      const response = await fetch('./api/index.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          title,
          description,
          link
        })
      });

      const result = await response.json();

      if (result.success) {
        const newResource = {
          id: result.id,
          title,
          description,
          link
        };

        resources.push(newResource);
        renderTable();
        resourceForm.reset();
      } else {
        alert(result.message || 'Failed to add resource.');
      }
    }
  } catch (error) {
    console.error('Error saving resource:', error);
    alert('An error occurred while saving the resource.');
  }
}

/**
 * Handles clicks on Edit/Delete buttons using event delegation.
 * @param {MouseEvent} event
 */
async function handleTableClick(event) {
  const target = event.target;

  if (target.classList.contains('delete-btn')) {
    const id = target.dataset.id;

    try {
      const response = await fetch(`./api/index.php?id=${id}`, {
        method: 'DELETE'
      });

      const result = await response.json();

      if (result.success) {
        resources = resources.filter(
          (resource) => Number(resource.id) !== Number(id)
        );
        renderTable();

        if (editingResourceId !== null && Number(editingResourceId) === Number(id)) {
          resourceForm.reset();
          editingResourceId = null;
          submitButton.textContent = 'Add Resource';
        }
      } else {
        alert(result.message || 'Failed to delete resource.');
      }
    } catch (error) {
      console.error('Error deleting resource:', error);
      alert('An error occurred while deleting the resource.');
    }
  }

  if (target.classList.contains('edit-btn')) {
    const id = target.dataset.id;
    const resource = resources.find(
      (item) => Number(item.id) === Number(id)
    );

    if (!resource) return;

    titleInput.value = resource.title || '';
    descriptionInput.value = resource.description || '';
    linkInput.value = resource.link || '';

    editingResourceId = Number(id);
    submitButton.textContent = 'Update Resource';
  }
}

/**
 * Loads resources from API and initializes event listeners.
 */
async function loadAndInitialize() {
  try {
    const response = await fetch('./api/index.php');
    const result = await response.json();

    if (result.success) {
      resources = result.data || [];
      renderTable();
    } else {
      alert(result.message || 'Failed to load resources.');
    }

    resourceForm.addEventListener('submit', handleAddResource);
    resourcesTbody.addEventListener('click', handleTableClick);
  } catch (error) {
    console.error('Error loading resources:', error);
    alert('An error occurred while loading resources.');
  }
}

// --- Initial Page Load ---
loadAndInitialize();
