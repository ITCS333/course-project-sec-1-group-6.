 let topics = [];

const newTopicForm = document.getElementById("new-topic-form");
const topicListContainer = document.getElementById("topic-list-container");

function createTopicArticle(topic) {
    const article = document.createElement("article");

    const h3 = document.createElement("h3");
    const link = document.createElement("a");
    link.href = "topic.html?id=" + topic.id;
    link.textContent = topic.subject;
    h3.appendChild(link);

    const footer = document.createElement("footer");
    footer.textContent = "Posted by: " + topic.author + " on " + topic.created_at;

    const div = document.createElement("div");

    const editButton = document.createElement("button");
    editButton.textContent = "Edit";
    editButton.className = "edit-btn";
    editButton.dataset.id = topic.id;

    const deleteButton = document.createElement("button");
    deleteButton.textContent = "Delete";
    deleteButton.className = "delete-btn";
    deleteButton.dataset.id = topic.id;

    div.appendChild(editButton);
    div.appendChild(deleteButton);

    article.appendChild(h3);
    article.appendChild(footer);
    article.appendChild(div);

    return article;
}

function renderTopics() {
    topicListContainer.innerHTML = "";

    for (let i = 0; i < topics.length; i++) {
        topicListContainer.appendChild(createTopicArticle(topics[i]));
    }
}

async function handleCreateTopic(event) {
    event.preventDefault();

    const subject = document.getElementById("topic-subject").value;
    const message = document.getElementById("topic-message").value;
    const button = document.getElementById("create-topic");

    if (button.dataset.editId) {
        await handleUpdateTopic(button.dataset.editId, {
            subject: subject,
            message: message
        });

        button.textContent = "Create Topic";
        delete button.dataset.editId;
        newTopicForm.reset();
        return;
    }

    const response = await fetch("./api/index.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            subject: subject,
            message: message,
            author: "Fatima Saleh"
        })
    });

    const result = await response.json();

    if (result.success) {
        topics.push({
            id: result.id,
            subject: subject,
            message: message,
            author: "Fatima Saleh",
            created_at: new Date().toISOString().slice(0, 19).replace("T", " ")
        });

        renderTopics();
        newTopicForm.reset();
    }
}

async function handleUpdateTopic(id, fields) {
    const response = await fetch("./api/index.php", {
        method: "PUT",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            id: id,
            subject: fields.subject,
            message: fields.message
        })
    });

    const result = await response.json();

    if (result.success) {
        for (let i = 0; i < topics.length; i++) {
            if (topics[i].id == id) {
                topics[i].subject = fields.subject;
                topics[i].message = fields.message;
            }
        }

        renderTopics();
    }
}

async function handleTopicListClick(event) {
    if (event.target.classList.contains("delete-btn")) {
        const id = event.target.dataset.id;

        const response = await fetch("./api/index.php?id=" + id, {
            method: "DELETE"
        });

        const result = await response.json();

        if (result.success) {
            topics = topics.filter(function(topic) {
                return topic.id != id;
            });

            renderTopics();
        }
    }

    if (event.target.classList.contains("edit-btn")) {
        const id = event.target.dataset.id;

        const topic = topics.find(function(topic) {
            return topic.id == id;
        });

        document.getElementById("topic-subject").value = topic.subject;
        document.getElementById("topic-message").value = topic.message;

        const button = document.getElementById("create-topic");
        button.textContent = "Update Topic";
        button.dataset.editId = id;
    }
}

async function loadAndInitialize() {
    const response = await fetch("./api/index.php");
    const result = await response.json();

    if (result.success) {
        topics = result.data;
        renderTopics();
    }

    newTopicForm.addEventListener("submit", handleCreateTopic);
    topicListContainer.addEventListener("click", handleTopicListClick);
}

loadAndInitialize();
