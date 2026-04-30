let currentTopicId = null;
let currentReplies = [];

const topicSubject = document.getElementById("topic-subject");
const opMessage = document.getElementById("op-message");
const opFooter = document.getElementById("op-footer");
const replyListContainer = document.getElementById("reply-list-container");
const replyForm = document.getElementById("reply-form");
const newReplyText = document.getElementById("new-reply");

function getTopicIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get("id");
}

function renderOriginalPost(topic) {
  topicSubject.textContent = topic.subject;
  opMessage.textContent = topic.message;
  opFooter.textContent =
    "Posted by: " + topic.author + " on " + topic.created_at;
}

function createReplyArticle(reply) {
  const article = document.createElement("article");

  const p = document.createElement("p");
  p.textContent = reply.text;

  const footer = document.createElement("footer");
  footer.textContent =
    "Posted by: " + reply.author + " on " + reply.created_at;

  const div = document.createElement("div");

  const deleteBtn = document.createElement("button");
  deleteBtn.textContent = "Delete";
  deleteBtn.className = "delete-reply-btn";
  deleteBtn.dataset.id = reply.id;

  div.appendChild(deleteBtn);

  article.appendChild(p);
  article.appendChild(footer);
  article.appendChild(div);

  return article;
}

function renderReplies() {
  replyListContainer.innerHTML = "";

  for (let i = 0; i < currentReplies.length; i++) {
    const article = createReplyArticle(currentReplies[i]);
    replyListContainer.appendChild(article);
  }
}

async function handleAddReply(event) {
  event.preventDefault();

  const replyText = newReplyText.value.trim();

  if (replyText === "") return;

  const response = await fetch("./api/index.php?action=reply", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      topic_id: currentTopicId,
      author: "Fatima Saleh",
      text: replyText
    })
  });

  const result = await response.json();

  if (result.success) {
    currentReplies.push(result.data);
    renderReplies();
    newReplyText.value = "";
  }
}

async function handleReplyListClick(event) {
  if (event.target.classList.contains("delete-reply-btn")) {
    const id = event.target.dataset.id;

    const response = await fetch(
      "./api/index.php?action=delete_reply&id=" + id,
      { method: "DELETE" }
    );

    const result = await response.json();

    if (result.success) {
      currentReplies = currentReplies.filter(function (reply) {
        return reply.id != id;
      });

      renderReplies();
    }
  }
}

async function initializePage() {
  currentTopicId = getTopicIdFromURL();

  if (!currentTopicId) {
    topicSubject.textContent = "Topic not found.";
    return;
  }

  const [topicRes, repliesRes] = await Promise.all([
    fetch("./api/index.php?id=" + currentTopicId),
    fetch(
      "./api/index.php?action=replies&topic_id=" + currentTopicId
    )
  ]);

  const topicData = await topicRes.json();
  const repliesData = await repliesRes.json();

  if (topicData.success) {
    renderOriginalPost(topicData.data);
    currentReplies = repliesData.data || [];
    renderReplies();

    replyForm.addEventListener("submit", handleAddReply);
    replyListContainer.addEventListener(
      "click",
      handleReplyListClick
    );
  } else {
    topicSubject.textContent = "Topic not found.";
  }
}

initializePage();
