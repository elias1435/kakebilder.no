

// Add image upload (only for 'kake-bilder' category) and text input (for all products)
add_action('woocommerce_before_add_to_cart_button', 'add_custom_image_text_fields');

function add_custom_image_text_fields() {
    global $product;
    
    $product_id = $product->get_id();
    //$categories = array('kake-bilder', 'anledninger', 'brannmann', 'dinosaur', 'dinosaur', 'meldinger', 'temabilder'); // Add more categories as needed
	//$is_kakebilder = has_term($categories, 'product_cat', $product_id);

	$featured_image = get_the_post_thumbnail_url($product->get_id(), 'full'); // Get featured image
    
    ?>

    <div class="custom-upload-container">
        <label for="custom_text">Enter Your Custom Text:</label>
        <input type="text" id="custom_text" name="custom_text" style="width:100%; padding:5px; margin-top:5px;">
		
		<div class="bt-space10 upload-image-field">
			<label for="image_upload">Upload Your Image:</label>
			<input type="file" id="image_upload" accept="image/*">			
		</div>

        <div id="preview_container">
			<!-- Hidden Input to Store PHP Featured Image -->
			<input type="hidden" id="featured_image" value="<?php echo esc_url($featured_image); ?>">

			<!-- Visible Canvas (Replace Preview Image Container) -->
			<canvas id="canvas" width="300" height="300" style="border:1px solid #ddd; border-radius: 100%;"></canvas>
        </div>
		
		<div class="bt-space10">
			<label for="zoom_slider">Zoom:</label>
			<input type="range" id="zoom_slider" min="0.5" max="2" step="0.1" value="1">	
		</div>

        <button type="button" id="generate_final_image" class="button">Generate Final Image</button>
        <p id="upload_status">Image generated successfully!</p>
        <input type="hidden" name="custom_uploaded_image" id="custom_uploaded_image" value="" />
    </div>





<script>

jQuery(document).ready(function ($) {
    let previewImage = $("#preview_image");
    let canvas = document.getElementById("canvas");
    let ctx = canvas.getContext("2d");

    let textX = canvas.width / 2, textY = canvas.height / 2;
    let isDraggingText = false, offsetX, offsetY;

    let uploadedImage = null;
    let imageScale = 1; // Default scale = 1 (No zoom)
    let imgX = 0, imgY = 0, imgWidth = canvas.width, imgHeight = canvas.height;
    let isDraggingImage = false;
    let imgOffsetX = 0, imgOffsetY = 0;

    // Get the featured image URL from PHP
    let defaultImage = $("#featured_image").val();

    // Disable the button initially
    $("#generate_final_image").prop("disabled", true);

    /** 
     * Load image onto the canvas
     * @param {string} src - Image URL or DataURL 
     */
    function loadImage(src) {
        let img = new Image();
        img.crossOrigin = "Anonymous"; // Prevent CORS issue
        img.src = src;

        img.onload = function () {
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            // Maintain proper scaling
            let scale = Math.max(canvas.width / img.width, canvas.height / img.height);
            imgWidth = img.width * scale * imageScale;
            imgHeight = img.height * scale * imageScale;

            // Draw the image with draggable position
            ctx.drawImage(img, imgX, imgY, imgWidth, imgHeight);
            drawText();

            // Update global uploadedImage reference
            uploadedImage = img;
        };
    }

    /**
     * Draw text on the canvas
     */
    function drawText() {
        let textValue = $("#custom_text").val();
        ctx.font = "bold 24px Arial";
        ctx.fillStyle = "black";
        ctx.textAlign = "center";
        ctx.fillText(textValue, textX, textY);
    }

    // Load the featured image if no uploaded image
    loadImage(defaultImage);

    // Handle text input updates
    $("#custom_text").on("input", function () {
        loadImage(uploadedImage ? uploadedImage.src : defaultImage);
    });

    // Handle Image Upload
    $("#image_upload").on("change", function (event) {
        let file = event.target.files[0];
        if (file) {
            let reader = new FileReader();
            reader.onload = function (e) {
                uploadedImage = new Image();
                uploadedImage.crossOrigin = "Anonymous"; // Prevent CORS issue
                uploadedImage.src = e.target.result;

                uploadedImage.onload = function () {
                    imgX = 0; // Reset position
                    imgY = 0;
                    loadImage(uploadedImage.src);
                    $("#generate_final_image").prop("disabled", false); // Enable button
                };
            };
            reader.readAsDataURL(file);
        }
    });

    // Mouse Down - Start Dragging Image or Text
    $(canvas).on("mousedown", function (e) {
        let rect = canvas.getBoundingClientRect();
        let clickX = e.clientX - rect.left;
        let clickY = e.clientY - rect.top;

        // Check if clicking the image
        if (uploadedImage && clickX >= imgX && clickX <= imgX + imgWidth &&
            clickY >= imgY && clickY <= imgY + imgHeight) {
            isDraggingImage = true;
            imgOffsetX = clickX - imgX;
            imgOffsetY = clickY - imgY;
            return;
        }

        // Check if clicking the text
        if (clickX >= textX - 50 && clickX <= textX + 50 &&
            clickY >= textY - 10 && clickY <= textY + 10) {
            isDraggingText = true;
            offsetX = clickX - textX;
            offsetY = clickY - textY;
        }
    });

    // Mouse Move - Move Image or Text
    $(canvas).on("mousemove", function (e) {
        let rect = canvas.getBoundingClientRect();
        let moveX = e.clientX - rect.left;
        let moveY = e.clientY - rect.top;

        if (isDraggingImage) {
            imgX = moveX - imgOffsetX;
            imgY = moveY - imgOffsetY;
        }

        if (isDraggingText) {
            textX = moveX - offsetX;
            textY = moveY - offsetY;
        }

        if (isDraggingImage || isDraggingText) {
            loadImage(uploadedImage ? uploadedImage.src : defaultImage);
        }
    });

    // Mouse Up - Stop Dragging
    $(canvas).on("mouseup", function () {
        isDraggingImage = false;
        isDraggingText = false;
    });

    // Zoom Feature - Mouse Wheel
    $(canvas).on("wheel", function (event) {
        event.preventDefault();
        let zoomFactor = 0.1;
        if (event.originalEvent.deltaY < 0) {
            imageScale += zoomFactor; // Zoom in
        } else {
            imageScale = Math.max(0.5, imageScale - zoomFactor); // Zoom out (min scale 0.5)
        }
        loadImage(uploadedImage ? uploadedImage.src : defaultImage);
    });

    // Zoom Feature - Slider
    $("#zoom_slider").on("input", function () {
        imageScale = parseFloat($(this).val());
        loadImage(uploadedImage ? uploadedImage.src : defaultImage);
    });

    // Generate Final Image
    $("#generate_final_image").click(function () {
        let finalImage = canvas.toDataURL("image/png");
        $("#custom_uploaded_image").val(finalImage);
        $("#upload_status").show().text("Image generated successfully!");
        $(".type-product").addClass("product-added");
    });

    // Check if text input is required before enabling the generate button
    let textInput = $("#custom_text");
    let generateButton = $("#generate_final_image");

    function checkText() {
        let textValue = textInput.val().trim();
        generateButton.prop("disabled", textValue === "");
    }

    checkText();
    textInput.on("input", checkText);

    // Touch Events for Mobile Support
    $(canvas).on("touchstart", function (e) {
        let rect = canvas.getBoundingClientRect();
        let touch = e.touches[0];
        let touchX = touch.clientX - rect.left;
        let touchY = touch.clientY - rect.top;

        if (uploadedImage && touchX >= imgX && touchX <= imgX + imgWidth &&
            touchY >= imgY && touchY <= imgY + imgHeight) {
            isDraggingImage = true;
            imgOffsetX = touchX - imgX;
            imgOffsetY = touchY - imgY;
        }
    });

    $(canvas).on("touchmove", function (e) {
        if (isDraggingImage) {
            let rect = canvas.getBoundingClientRect();
            let touch = e.touches[0];
            imgX = touch.clientX - rect.left - imgOffsetX;
            imgY = touch.clientY - rect.top - imgOffsetY;
            loadImage(uploadedImage ? uploadedImage.src : defaultImage);
        }
    });

    $(canvas).on("touchend", function () {
        isDraggingImage = false;
    });
});


    </script>
    <?php
}

// Save Image & Text in Cart Data
add_filter('woocommerce_add_cart_item_data', 'save_custom_image_to_server', 10, 2);
function save_custom_image_to_server($cart_item_data, $product_id) {
    if (!empty($_POST['custom_uploaded_image'])) {
        $base64_image = $_POST['custom_uploaded_image'];
        
        // Decode Base64 Image
        $image_parts = explode(";base64,", $base64_image);
        if (count($image_parts) == 2) {
            $image_base64 = base64_decode($image_parts[1]);
            $upload_dir = wp_upload_dir();
            
            // Generate Unique Image Name
            $image_name = 'custom_product_' . uniqid() . '.png';
            $file_path = $upload_dir['path'] . '/' . $image_name;
            $file_url = $upload_dir['url'] . '/' . $image_name;

            // Save Image to Server
            file_put_contents($file_path, $image_base64);

            // Store File URL Instead of Base64
            $cart_item_data['custom_image'] = esc_url($file_url);

            // Also store the URL in WooCommerce session (to use later if needed)
            WC()->session->set('custom_uploaded_image', $file_url);
        }
    }

    if (!empty($_POST['custom_text'])) {
        $cart_item_data['custom_text'] = sanitize_text_field($_POST['custom_text']);
        WC()->session->set('custom_text', $_POST['custom_text']);
    }

    return $cart_item_data;
}



// Display in Cart & Checkout
add_filter('woocommerce_get_item_data', 'display_custom_image_in_cart', 10, 2);
function display_custom_image_in_cart($item_data, $cart_item) {
    if (!empty($cart_item['custom_text'])) {
        $item_data[] = ['name' => 'Custom Text', 'value' => $cart_item['custom_text']];
    }
    if (!empty($cart_item['custom_image'])) {
        $item_data[] = [
            'name' => 'Uploaded Image',
            'value' => '<img src="'.$cart_item['custom_image'].'" width="100">'
        ];
    }
    return $item_data;
}


// Save custom fields to order details
add_action('woocommerce_checkout_create_order_line_item', 'add_custom_fields_to_order', 10, 4);

function add_custom_fields_to_order($item, $cart_item_key, $values, $order) {
    if (isset($values['custom_text'])) {
        $item->add_meta_data('Custom Text', $values['custom_text']);
    }
    
    if (isset($values['custom_image'])) {
        $item->add_meta_data('Uploaded Image', '<img src="'.$values['custom_image'].'" width="100">');
    }
}

// Display the uploaded image with a download button in WooCommerce Admin Order Dashboard
add_action('woocommerce_admin_order_data_after_order_details', 'display_uploaded_image_in_admin_order_meta');

function display_uploaded_image_in_admin_order_meta($order) {
    $image_url = $order->get_meta('_custom_uploaded_image');

    if (!empty($image_url)) {
        echo '<p><strong>Custom Uploaded Image:</strong></p>';
        echo '<img src="' . esc_url($image_url) . '" style="width: 30px; height: 30px; border-radius: 100%; object-fit: cover;">';

        // Ensure a valid file extension before displaying the download button
        $file_extension = pathinfo($image_url, PATHINFO_EXTENSION);
        if (in_array(strtolower($file_extension), ['png', 'jpg', 'jpeg'])) {
            echo '<p><a href="' . esc_url($image_url) . '" download class="button button-primary">Download Image</a></p>';
        } else {
            echo '<p style="color: red;">Invalid image format.</p>';
        }
    }
}



// Attach the uploaded image in order emails (Admin & Customer)
add_action('woocommerce_email_order_meta', 'attach_custom_image_to_emails', 10, 3);

function attach_custom_image_to_emails($order, $sent_to_admin, $plain_text) {
    $image_url = $order->get_meta('_custom_uploaded_image');

    if (!empty($image_url)) {
        echo '<p><strong>Custom Uploaded Image:</strong></p>';
        echo '<img src="' . esc_url($image_url) . '" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">';
    }
}



// Send Image in Admin New Order Email
add_action('woocommerce_email_order_details', 'add_custom_image_to_admin_email', 10, 4);
function add_custom_image_to_admin_email($order, $sent_to_admin, $plain_text, $email) {
    if ($sent_to_admin) { // Only for Admin Emails
        $image_url = $order->get_meta('_custom_uploaded_image');

        if (!empty($image_url)) {
            echo '<p><strong>Custom Uploaded Image:</strong></p>';
            echo '<img src="' . esc_url($image_url) . '" style="width:40px; height:40px; border-radius:50%;">';
            echo '<p><a href="' . esc_url($image_url) . '" download style="display:inline-block;padding:10px 15px;color:#fff;background:#0073aa;text-decoration:none;border-radius:5px;">Download Image</a></p>';
        }
    }
}


// Send Image in Customer Order Processing Email
add_action('woocommerce_email_order_details', 'add_custom_image_to_customer_email', 10, 4);
function add_custom_image_to_customer_email($order, $sent_to_admin, $plain_text, $email) {
    if (!$sent_to_admin) { // Only for Customer Emails
        $image_url = $order->get_meta('_custom_uploaded_image');

        if (!empty($image_url)) {
            echo '<p><strong>Your Custom Uploaded Image:</strong></p>';
            echo '<img src="' . esc_url($image_url) . '" style="width:40px; height:40px; border-radius:50%;">';
        }
    }
}
