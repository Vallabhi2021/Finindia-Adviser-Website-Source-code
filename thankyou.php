<?php
$title = 'Thank You - FinIndia';
include("header/header.php");
?>

<section class="wrap-container content" style="padding:60px 0;text-align:center;">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-8">
        <div class="thankyou-card" style="background:#fff;padding:40px;border-radius:8px;box-shadow:0 8px 30px rgba(0,0,0,0.08);">
          <img src="<?php echo $base_url;?>src/img/thanks.gif" alt="Thank you" style="max-width:120px;margin-bottom:20px;">
          <!-- <h1 style="color:#1b2430;margin-bottom:10px;">Thank you!</h1> -->
          <p style="color:#4b5563;font-size:16px;">We have received your submission. Our team will get back to you shortly.</p>
          <div style="margin-top:20px;">
            <a href="<?php echo $base_url;?>" class="submit-btn" style="display:inline-block;padding:10px 20px;">Back to Home</a>
            <a href="<?php echo $base_url;?>" style="margin-left:15px;color:#6b7280;text-decoration:none;">Explore other services</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include("header/footer.php"); ?>