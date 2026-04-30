// ...existing code...
document.addEventListener('DOMContentLoaded', loadTasks);


// Load tasks from get_tasks.php when the page loads
function loadTasks() {
  fetch('/get_tasks.php')
    .then(response => response.text()) // Expecting HTML from CSV-based PHP
    .then(html => {
      document.getElementById('task-list').innerHTML = html;
    })
    .catch(error => {
      console.error('Error loading tasks:', error);
    });
}
function addTask() {
  const input = document.getElementById('new-task');
  const title = input.value.trim();

  // ...existing code...

  if (!title) {
    showMessage('Task title cannot be empty.', true);
    return;
  }

  fetch('/add_tasks.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: 'title=' + encodeURIComponent(title)
  })
  .then(response => response.text())
  .then(result => {
    // ...existing code...
    if (result.trim() === 'success') {
      showMessage('Task added successfully.');
      input.value = '';
      loadTasks();
    } else {
      showMessage(result, true);
    }
  })
  .catch(error => {
    console.error('Error adding task:', error);
    showMessage('Request failed.', true);
  });
}

function showMessage(text, isError = false) {
  const msg = document.getElementById('message');
  msg.textContent = text;
  msg.style.color = isError ? 'red' : 'green';
  setTimeout(() => msg.textContent = '', 3000);
}

function archiveTask(id) {
  fetch('/archive_task.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: 'id=' + encodeURIComponent(id)
  })
  .then(response => response.text())
  .then(result => {
    if (result.trim() === 'success') {
      showMessage('Task archived successfully.');
      loadTasks();
    } else {
      showMessage('Failed to archive task.', true);
    }
  })
  .catch(error => {
    console.error('Error archiving task:', error);
    showMessage('Request failed.', true);
  });
}


 //Place this in your task-list.js or a new script file

// Example: Task List Logic for task-list.js
function addTask() {
  const taskInput = document.getElementById('new-task');
  const taskList = document.getElementById('task-list');
  const taskText = taskInput.value.trim();
  if (taskText) {
    const li = document.createElement('li');
    li.textContent = taskText;
    taskList.appendChild(li);
    taskInput.value = '';
  }
}

