<?php
	// define title desc
	$title = 'FinIndia | Contact Us';
	//$desc = 'page description';

	// include header file
	include("header/header.php");
?>
	
<section class="gradiant-col-finindian-others wrap-container content">
  <div class="container-fluid wrap-container  ">
    <div class="row">
      <div class="col-md-12">
       <div class="main-content">
  
          <h3 class="mb-1">Contact Us</h3>
          <p>We Specialized Debt Equity, Risk Assessment, and Regulatory Advisory</p>
          

       </div>
       <div class="smart-img-others">
         <img src="<?php echo $base_url;?>src/img/contact.webp" class="img-fluid">
       </div>
      
        
      </div>
    </div>
  </div>
</section>
<div class="container-fluid wrap-container">
  <div class="row">
    <div class="col-md-12">
       <nav aria-label="breadcrumb">
          <ol class="breadcrumb FinIndiabreadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo $base_url;?>">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Contact Us</li>
          </ol>
        </nav>
      </div>
    </div>
  </div>

<div class="container-fluid wrap-container">
    <div class="row mb-5">
        <div class="col-md-6">
            


        <div class="left-section">
            <div class="office-info">
                <h3>Registered Office</h3>
                <p>SF-4C, Second Floor, Rishabh Ipex Mall, Patparganj,</p>
                <p>IP Extension, Delhi - 110092</p>
                <p>Email Us At: office@finindia.com</p>
                <p>Call Now: +91-9289098583</p>
            </div>
        </div>
</div>
  <div class="col-md-6">
        <form id="contactForm" action="<?php echo $base_url;?>sendmailer.php" method="post" onsubmit="return validateContactForm(this);" novalidate>
        <input type="hidden" name="form_type" value="contact">
        <div class="right-section">
            <h2>Connect With Us</h2>
            
            <div class="form-group">
                <label>Name</label>
                <input type="text" id="name" name="name" placeholder="" required>
                <small class="error" style="display:none;color:#d9534f;">Please enter your name</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="email" name="email" placeholder="" required>
                    <small class="error" style="display:none;color:#d9534f;">Please enter a valid email</small>
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" id="phone" name="phone" placeholder="" maxlength="10" pattern="\d{10}" required oninput="this.value=this.value.replace(/[^0-9]/g,'');">
                    <small class="error" style="display:none;color:#d9534f;">Please enter a 10-digit phone number</small>
                </div>
            </div>

            <div class="form-group">
                <label>Message</label>
                <textarea id="message" name="message" placeholder="" required></textarea>
                <small class="error" style="display:none;color:#d9534f;">Please enter a message</small>
            </div>

            <button type="submit" class="submit-btn">Submit</button>
        </div>
        </form>
    </div>

</div>
</div>



<?php include("header/footer.php"); ?>