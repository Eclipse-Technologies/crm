<?php
// follow_up_email.php

$contactName = $contact['name'] ?? 'there';
$companyName = $contact['company'] ?? 'your team';
$contactEmail = $contact['email'] ?? '';
$contactId = $contact['id'] ?? 0;
?>

<div class="module follow-up-email">
  <h3>Send Email</h3>
  <button onclick="generateEmail('followup')">Follow-Up After Call</button>
  <button onclick="generateEmail('intro')">Introductory Email</button>
  <button onclick="generateEmail('touchbase')">Touch Base Email</button>
  <button id="sendEmailBtn" onclick="sendFollowUpEmail()">Send Email</button>

  <textarea id="followUpEmail" rows="12" class="email-textarea"></textarea>
</div>

<script>
const contactName = "<?= htmlspecialchars($contactName, ENT_QUOTES) ?>";
const contactEmail = "<?= htmlspecialchars($contactEmail, ENT_QUOTES) ?>";
let currentEmailType = '';

function generateEmail(type) {
  currentEmailType = type;

  let body = '';
  switch (type) {
    case 'followup':
      body = `Hi ${contactName},

It was great speaking with you earlier. I wanted to follow up on our conversation and thank you for taking the time to connect.

At Eclipse Water Technologies, we’re committed to providing responsive, local service without the delays or complications of cross-border logistics. Whether it’s a complex treatment challenge or a routine system check, our team is here to support you.

If you have any questions or would like to explore next steps, feel free to reach out anytime.

Best regards,  
Robert Lee  CET PMP  
Managing Director  
Eclipse Water Technologies  
📞 647-355-0944  
📧 rlee@eclipsewatertechnologies.com  
🌐 https://eclipsewatertechnologies.com`;
      break;

    case 'intro':
      body = `Hi ${contactName},

I wanted to introduce you to Eclipse Water Technologies — a local provider of industrial water and wastewater solutions.

We specialize in everything from advanced treatment systems to simple filter replacements, and our team is known for responsive service and technical expertise. Being local means no border delays or tariffs — just fast, reliable support.

We’d love to learn more about your needs and explore how we can help.

Best regards,  
Robert Lee  CET PMP  
Managing Director  
Eclipse Water Technologies  
📞 647-355-0944  
📧 rlee@eclipsewatertechnologies.com  
🌐 https://eclipsewatertechnologies.com`;
      break;

    case 'touchbase':
      body = `Hi ${contactName},

I hope things are going well on your end. I just wanted to touch base and see if there’s anything you might need support with regarding your water or wastewater systems.

At Eclipse Water Technologies, we’re always happy to reconnect and help — whether it’s a quick filter change or a more involved project.

Feel free to reach out anytime if you'd like to chat or explore options.

Best regards,  
Robert Lee  CET PMP  
Managing Director  
Eclipse Water Technologies  
📞 647-355-0944  
📧 rlee@eclipsewatertechnologies.com  
🌐 https://eclipsewatertechnologies.com`;
      break;
  }

  document.getElementById('followUpEmail').value = body;
}

function sendFollowUpEmail() {
  const emailBody = document.getElementById('followUpEmail').value.trim();
  const subjectLine = "Industrial Water Treatment - Eclipse Water Technologies";

  if (!contactEmail) {
    alert("No email address found for this contact.");
    return;
  }

  if (!emailBody) {
    alert("Email body is empty. Please generate or write your message.");
    return;
  }

  const mailtoLink = `mailto:${contactEmail}?subject=${encodeURIComponent(subjectLine)}&body=${encodeURIComponent(emailBody)}`;
  window.location.href = mailtoLink;
}
</script>