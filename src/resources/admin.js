let resources = [];
let editMode = false;
let currentEditId = null;

const resourceForm = document.querySelector('#resource-form');
const resourcesTbody = document.querySelector('#resources-tbody');
const titleInput = document.querySelector('#resource-title');
const descriptionInput = document.querySelector('#resource-description');
const linkInput = document.querySelector('#resource-link');
const submitButton = document.querySelector('#add-resource');

function createResourceRow(resource) {
  const tr = document.createElement('tr');

  const titleTd = document.createElement('td');
  titleTd.textContent = resource.title;

  const descriptionTd = document.createElement('td');
  descriptionTd.textContent = resource.description || '';

  const linkTd = document.createElement('td');
  const link = document.createElement('a');
  link.href = resource.link;
  link.target = '_blank';
  link.textContent = resource.link;
  linkTd.appendChild(link);

  const actionsTd = document.createElement('td');

  const editButton = document.createElement('button');
  editButton.className = 'edit-btn';
  editButton.dataset.id = resource.id;
  editButton.textContent = 'Edit';

  const deleteButton = document.createElement('button');
  deleteButton.className = 'delete-btn';
  deleteButton.dataset.id = resource.id;
  deleteButton.textContent = 'Delete';

  actionsTd.appendChild(editButton);
  actionsTd.appendChild(deleteButton);

  tr.appendChild(titleTd);
  tr.appendChild(descriptionTd);
  tr.appendChild(linkTd);
  tr.appendChild(actionsTd);

  return tr;
}

function renderTable() {
  resourcesTbody.innerHTML = '';

  for (const resource of resources) {
    const tr = createResourceRow(resource);
    resourcesTbody.appendChild(tr);
  }
}

async function handleAddResource(event) {
  event.preventDefault();

  const title = titleInput.value.trim();
  const description = descriptionInput.value.trim();
  const link = linkInput.value.trim();

  if (!title || !link) return;

  if (editMode && currentEditId !== null) {
    const response = await fetch('./api/index.php', {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        id: currentEditId,
        title,
        description,
        link
      })
    });

    const result = await response.json();

    if (result.success) {
      resources = resources.map((resource) =>
        String(resource.id) === String(currentEditId)
          ? {
              ...resource,
              id: currentEditId,
              title,
              description,
              link
            }
          : resource
      );

      renderTable();
      resourceForm.reset();
      editMode = false;
      currentEditId = null;
      submitButton.textContent = 'Add Resource';
    }

    return;
  }

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
    resources.push({
      id: result.id,
      title,
      description,
      link
    });

    renderTable();
    resourceForm.reset();
  }
}

async function handleTableClick(event) {
  const target = event.target;

  if (target.classList.contains('delete-btn')) {
    const id = target.dataset.id;

    const response = await fetch(`./api/index.php?id=${id}`, {
      method: 'DELETE'
    });

    const result = await response.json();

    if (result.success) {
      resources = resources.filter(
        (resource) => String(resource.id) !== String(id)
      );
      renderTable();
    }
  }

  if (target.classList.contains('edit-btn')) {
    const id = target.dataset.id;
    const resource = resources.find(
      (item) => String(item.id) === String(id)
    );

    if (!resource) return;

    titleInput.value = resource.title;
    descriptionInput.value = resource.description || '';
    linkInput.value = resource.link;
    submitButton.textContent = 'Update Resource';

    editMode = true;
    currentEditId = id;
  }
}

async function loadAndInitialize() {
  const response = await fetch('./api/index.php');
  const result = await response.json();

  resources = result.success ? result.data : [];
  renderTable();

  resourceForm.addEventListener('submit', handleAddResource);
  resourcesTbody.addEventListener('click', handleTableClick);
}

loadAndInitialize();