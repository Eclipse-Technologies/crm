
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Evoqua SDI Contract Cancellation Tool</title>
  <link rel="stylesheet" href="styles.css?v=20251002">
</head>
<body>
<div class="container">
  
  
	<table>
		<caption>Suggestions on how to Cancel your contract - What you can do</caption>
		
		<tr>
			<td> Review your specific order or contract for any additional terms or amendments.<br>
				<br>
				Send a written notice (email or letter) to Evoqua’s official contact stating your intent and the reason for cancellation.<br>
				<br>
				Keep records of all correspondence and responses.</td>
		</tr>
			
			<th> How to cancel contract</th>
			<th> Contact Information</th>
		</tr>
		<tr>
			<td> Simply enter your informaiton in the fields below <br><br> Click "Generate Letter"</td>

			<td> Either email to<br><br> <a href="mailto:CentralAP@evoqua.com">CentralAP</a> 
				or<br><br>Fax to <br>(905) 890-6357 </td>

	</table>
	
	
   <!-- Termination Reason Table -->
   <section id="cancellation-form">
    <table>
	        <caption>Termination Reason Options</caption>
        <tr>
           <th>Reason</th>
            <th>Description</th>
        </tr>
        <tr>
          
            <td>End of Term</td>
            <td>For leased or rented equipment, the initial term automatically renews unless cancelled in writing by you or Evoqua.<br>
				<br>
				Notice Period: <br><br>You must provide written notice of cancellation not sooner than <b><u>three months</b></u> and <b><u>not later than one month before the end of the initial or any renewal term.</b></u><br>
				<br>	
				Obligations: <br><br>Cancelling service in writing before the end of the term does not relieve you of your obligation to pay the monthly rental/service charge for the full term.<br>
				<br>
				Return of Equipment: <br><br>Upon expiration or termination, you must make the leased equipment available for Evoqua to remove. </td>
        </tr>
        <tr>
            
            <td>Material Breach</td>
            <td>Either party (you or Evoqua) can terminate the agreement if the other party materially breaches the contract (e.g., fails to fulfill major obligations, files for bankruptcy).
	<br><br>Process: You must issue a written notice of breach and allow a 30-day period for the other party to cure (fix) the breach. <br><br>If the breach isn’t cured within 30 days, you can terminate the contract.
<br><br>Obligations: If you suspend an order for 90+ days without a change order, Evoqua may terminate with 15 days’ written notice and is entitled to payment for work performed up to the termination date.</td>
        </tr>
    </table>
	
	
	
  <h1>Evoqua SDI Contract Cancellation Tool</h1>

  <section id="cancellation-form">
    <h2>Generate Your Cancellation Letter</h2>
    <form id="generate-letter-form">
      <div class="form-grid">
        <!-- Form fields -->
        <div class="form-group">
          <label for="account-number">Account Number</label>
          <input type="text" id="account-number" required>
        </div>
        <div class="form-group">
          <label for="contract-number">Quote/Contract Number</label>
          <input type="text" id="contract-number" required>
        </div>
        <div class="form-group">
          <label for="contract-end-date">Contract End Date</label>
          <input type="date" id="contract-end-date" required>
        </div>
        <div class="form-group">
          <label for="termination-reason">Reason for Termination</label>
          <select id="termination-reason" required>
            <option value="">Select reason</option>
            <option value="end-of-term">End of Term</option>
            <option value="material-breach">Material Breach</option>
          </select>
        </div>
        <div class="form-group">
          <label for="your-name">Your Name</label>
          <input type="text" id="your-name" required>
        </div>
        <div class="form-group">
          <label for="your-position">Your Position/Company</label>
          <input type="text" id="your-position" required>
        </div>
        <div class="form-group">
          <label for="your-contact">Your Contact Information</label>
          <input type="text" id="your-contact" required>
        </div>
      </div>
      <button type="submit">Generate Letter</button>
    </form>
  </section>
  
<style>
    .table-container {
        display: flex;
        gap: 20px;
        align-items: flex-start;
        flex-wrap: wrap; /* responsive for smaller screens */
    }

    table {
        width: 45%;
        border-collapse: collapse;
        font-family: Arial, sans-serif;
        background-color: #e6f0ff; /* secondary background */
        color: #004080; /* primary text color */
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }

    caption {
        font-weight: bold;
        font-size: 1.2em;
        padding: 10px;
        background-color: #004080; /* primary color */
        color: #ffffff; /* white text */
    }

    th, td {
        border: 1px solid #cccccc; /* accent border */
        padding: 8px 12px;
        text-align: left;
    }

    th {
        background-color: #004080; /* primary color */
        color: #ffffff;
    }

    tr:nth-child(even) {
        background-color: #f9f9f9; /* subtle alternate row */
    }

    @media (max-width: 768px) {
        table {
            width: 100%; /* stack tables on small screens */
        }
    }
</style>

<div class="table-container">
   
 


  <section id="letter-output" class="hidden">
    <h2>Your Cancellation Letter</h2>
    <div id="generated-letter"></div>
    <br>
    <button class="btn-outline" id="download-word">Download as Word</button>
    <button class="btn-outline" id="download-pdf">Download as PDF</button>
  </section>
</div>


<script>

window.addEventListener('load', function () {
  const form = document.getElementById('generate-letter-form');
  const outputSection = document.getElementById('letter-output');
  const letterDiv = document.getElementById('generated-letter');
  const downloadWordBtn = document.getElementById('download-word');
  const downloadPdfBtn = document.getElementById('download-pdf');

  // Generate Letter
  form.addEventListener('submit', function (e) {
    e.preventDefault();

    const accountNumber = document.getElementById('account-number').value.trim();
    const contractNumber = document.getElementById('contract-number').value.trim();
    const contractEndDate = document.getElementById('contract-end-date').value;
    const terminationReason = document.getElementById('termination-reason').value;
    const yourName = document.getElementById('your-name').value.trim();
    const yourPosition = document.getElementById('your-position').value.trim();
    const yourContact = document.getElementById('your-contact').value.trim();

    let noticeText = terminationReason === 'end-of-term'
      ? `We are providing this notice for end of term termination, in accordance with Section 10. The effective termination date will be ${contractEndDate}.`
      : `We are providing this notice due to material breach of contract, in accordance with Section 10. The effective termination date will be ${contractEndDate}.`;

    const letter = `
      <strong>Subject:</strong> Notice of Termination – SDI Service Agreement ${accountNumber} / ${contractNumber}<br><br>
      To: Evoqua Water Technologies Ltd.<br><br>
      Dear Evoqua Team,<br><br>
      ${noticeText}<br><br>
      We acknowledge our obligation to:<br>
      - Continue payment of the monthly rental service charge for the remainder of the term, if applicable.<br>
      - Promptly make all Evoqua-owned equipment available for removal upon expiration or termination.<br>
      - Settle all outstanding invoices and charges.<br><br>
      Please confirm receipt of this notice and provide instructions for equipment return and final settlement.<br><br>
      Thank you for your cooperation.<br><br>
      Sincerely,<br>
      ${yourName}<br>
      ${yourPosition}<br>
      ${yourContact}
    `;

    letterDiv.innerHTML = letter;
    outputSection.classList.remove('hidden');
    outputSection.scrollIntoView({ behavior: 'smooth' });
  });

  // ✅ Download as Word (with proper HTML structure and UTF-8 encoding)
  downloadWordBtn.addEventListener('click', function () {
    const htmlContent = `
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="UTF-8">
        <title>Cancellation Letter</title>
      </head>
      <body>
        ${letterDiv.innerHTML}
      </body>
      </html>
    `;
    const blob = new Blob([htmlContent], { type: 'application/msword;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'Cancellation_Letter.doc';
    link.click();
  });

  // ✅ Download as PDF using jsPDF
  downloadPdfBtn.addEventListener('click', function () {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.html(letterDiv, {
      callback: function (doc) {
        doc.save('Cancellation_Letter.pdf');
      },
      x: 10,
      y: 10
    });
  });
});

</script>
</body>
</html>
