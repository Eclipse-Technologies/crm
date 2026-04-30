
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('generate-letter-form');
  const outputSection = document.getElementById('letter-output');
  const letterDiv = document.getElementById('generated-letter');
  const downloadWordBtn = document.getElementById('download-word');
  const downloadPdfBtn = document.getElementById('download-pdf');

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    // Collect form data
    const accountNumber = document.getElementById('account-number').value.trim();
    const contractNumber = document.getElementById('contract-number').value.trim();
    const contractEndDate = document.getElementById('contract-end-date').value;
    const terminationReason = document.getElementById('termination-reason').value;
    const yourName = document.getElementById('your-name').value.trim();
    const yourPosition = document.getElementById('your-position').value.trim();
    const yourContact = document.getElementById('your-contact').value.trim();

    // Generate notice period text
    let noticeText = '';
    if (terminationReason === 'end-of-term') {
      noticeText = `We are providing this notice for end of term termination, in accordance with Section 10. Notice is being given within the required window (not sooner than three (3) months nor later than one (1) month before the end of the initial or renewal term). The effective termination date will be ${contractEndDate}.`;
    } else if (terminationReason === 'material-breach') {
      noticeText = `We are providing this notice due to material breach of contract, in accordance with Section 10. Notice includes a 30-day cure period. If the breach is not remedied within this period, the effective termination date will be ${contractEndDate}.`;
    } else {
      noticeText = 'Please select a valid reason for termination.';
    }

    // Generate letter
    const letter = `
      <strong>Subject:</strong> Notice of Termination – SDI Service Agreement ${accountNumber ? accountNumber : '[Account Number]'} / ${contractNumber ? contractNumber : '[Contract Number]'}<br><br>
      To: Evoqua Water Technologies Ltd.<br><br>
      Dear Evoqua Team,<br><br>
      ${noticeText}<br><br>
      We acknowledge our obligation to:<br>
      - Continue payment of the monthly rental service charge for the remainder of the term, if applicable.<br>
      - Promptly make all Evoqua-owned equipment available for removal upon expiration or termination, and grant Evoqua access for retrieval as required.<br>
      - Settle all outstanding invoices and charges as per the agreement.<br><br>
      Please confirm receipt of this notice and provide instructions for equipment return and final settlement. Kindly advise if there are any additional steps required to ensure compliance with the terms of our agreement.<br><br>
      Thank you for your cooperation.<br><br>
      Sincerely,<br>
      ${yourName}<br>
      ${yourPosition}<br>
      ${yourContact}
    `;

    // Display letter
    letterDiv.innerHTML = letter;
    outputSection.style.display = 'block';
    outputSection.scrollIntoView({ behavior: 'smooth' });
  });

  // Placeholder download functions
  downloadWordBtn.addEventListener('click', function () {
    alert('Word download functionality coming soon!');
    // Implement Word export logic here
  });

  downloadPdfBtn.addEventListener('click', function () {
    alert('PDF download functionality coming soon!');
    // Implement PDF export logic here
  });
});
