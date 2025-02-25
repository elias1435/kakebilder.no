<?php
/**
 * Theme functions and definitions
 *
 * @package HelloElementorChild
 */

/**
 * Load child theme css and optional scripts
 *
 * @return void
 */
function hello_elementor_child_enqueue_scripts() {
	wp_enqueue_style(
		'hello-elementor-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		[
			'hello-elementor-theme-style',
		],
		'1.0.0'
	);
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_enqueue_scripts', 20 );

// Redirect after add to cart page
function redirect_to_cart_after_add_to_cart($url) {
    return wc_get_cart_url();
}
add_filter('woocommerce_add_to_cart_redirect', 'redirect_to_cart_after_add_to_cart');


// Set default quantity to 15 on product pages
add_filter('woocommerce_quantity_input_args', 'set_default_quantity', 10, 2);
function set_default_quantity($args, $product) {
    if (is_product()) {
        $args['input_value'] = 15; // Default quantity
    }
    return $args;
}

// Automatically update cart when quantity changes
add_action('wp_footer', 'ajax_update_cart_quantity');
function ajax_update_cart_quantity() {
    if (is_cart()) : ?>
        <script type="text/javascript">
		jQuery(function ($) {
			$(document).on('change', 'input.qty', function () {
				let qty = $(this).val();
				let cartItemKey = $(this).attr("name").match(/\[(.*?)\]/)[1]; // Extract cart key

				$.ajax({
					type: "POST",
					url: "<?php echo admin_url('admin-ajax.php'); ?>",
					data: {
						action: "update_cart_quantity",
						cart_key: cartItemKey,
						quantity: qty
					},
					success: function (response) {
						$(document.body).trigger("wc_update_cart"); // Refresh cart totals
						$(document.body).trigger("wc_fragment_refresh"); // Refresh mini cart
					}
				});
			});
		});

        </script>
    <?php endif;
}

// Handle AJAX request to update cart quantity
add_action('wp_ajax_update_cart_quantity', 'update_cart_quantity');
add_action('wp_ajax_nopriv_update_cart_quantity', 'update_cart_quantity');

function update_cart_quantity() {
    if (isset($_POST['cart_key']) && isset($_POST['quantity'])) {
        $cart = WC()->cart->get_cart();
        $cart_item_key = sanitize_text_field($_POST['cart_key']);
        $quantity = (int) $_POST['quantity'];

        if (isset($cart[$cart_item_key])) {
            WC()->cart->set_quantity($cart_item_key, $quantity, true);
            WC()->cart->calculate_totals();
        }
    }
    wp_die();
}






// custom js
function enqueue_custom_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script(
        'custom-image-upload',
        get_stylesheet_directory_uri() . '/js/custom-image-upload.js', 
        array('jquery'),
        false,
        true
    );
    wp_localize_script('custom-image-upload', 'ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_custom_scripts');

/******************************************************
 * ************ Custom Image Upload *******************
 */

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
        <label for="custom_text">Skriv inn din egendefinerte tekst</label>
        <input type="text" id="custom_text" name="custom_text" style="width:100%; padding:5px; margin-top:5px;">
		
		<div class="bt-space10 upload-image-field">
			<label for="image_upload">Last opp ditt bilde</label>
			<input type="file" id="image_upload" accept="image/*">			
		</div>

        <div id="preview_container">
			<!-- Hidden Input to Store PHP Featured Image -->
			<input type="hidden" id="featured_image" value="<?php echo esc_url($featured_image); ?>">

			<!-- Visible Canvas (Replace Preview Image Container) -->
			<canvas id="canvas" width="300" height="300" style="border:1px solid #000; border-radius: 100%;"></canvas>
        </div>
		
		<div class="bt-space10 zoom-handle">
			<label for="zoom_slider">Zoom</label>
			<input type="range" id="zoom_slider" min="0.5" max="2" step="0.1" value="1">	
		</div>

        <button type="button" id="generate_final_image" class="button">Generer endelig bilde</button>
        <p id="upload_status">Bilde generert vellykket!</p>
        <input type="hidden" name="custom_uploaded_image" id="custom_uploaded_image" value="" />
    </div>





<script>

jQuery(document).ready(function ($) {
    let canvas = document.getElementById("canvas");
    let ctx = canvas.getContext("2d");

    let textX = canvas.width / 2, textY = canvas.height / 2;
    let isDraggingText = false, textOffsetX, textOffsetY;

    let uploadedImage = null;
    let imageScale = 1; // Default scale = 1 (No zoom)
    let imgX = 0, imgY = 0, imgWidth = canvas.width, imgHeight = canvas.height;
    let isDraggingImage = false;
    let imgOffsetX = 0, imgOffsetY = 0;

    // Get the featured image URL from WooCommerce
    let defaultImage = $("#featured_image").val();

    // Get product category and check if zoom should be disabled
    let isKakeBilder = $("div").hasClass("product_cat-kake-bilder");
    let productCategory = $("#product_category").val();
    let isZoomDisabled = (productCategory === "temabilder" || productCategory === "product_cat-temabilder");

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

            let scale = Math.max(canvas.width / img.width, canvas.height / img.height);
            imgWidth = img.width * scale * imageScale;
            imgHeight = img.height * scale * imageScale;

            ctx.drawImage(img, imgX, imgY, imgWidth, imgHeight);
            drawText(); // Ensure text remains on top

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
        if (!file) return;

        let reader = new FileReader();
        reader.onload = function (e) {
            uploadedImage = new Image();
            uploadedImage.crossOrigin = "Anonymous";
            uploadedImage.src = e.target.result;

            uploadedImage.onload = function () {
                imgX = 0; // Reset position
                imgY = 0;
                loadImage(uploadedImage.src);
            };
        };
        reader.readAsDataURL(file);
    });


	
	
$(canvas).on("mousedown", function (e) {
    if (!isKakeBilder) return; // Disable dragging if not product_cat-kake-bilder

    let rect = canvas.getBoundingClientRect();
    let clickX = e.clientX - rect.left;
    let clickY = e.clientY - rect.top;

	
    if (uploadedImage && clickX >= imgX && clickX <= imgX + imgWidth &&
        clickY >= imgY && clickY <= imgY + imgHeight) {
        isDraggingImage = true;
        imgOffsetX = clickX - imgX;
        imgOffsetY = clickY - imgY;
    }
});


$(canvas).on("mousemove", function (e) {
    if (!isKakeBilder) return; // Disable dragging if not product_cat-kake-bilder

    let rect = canvas.getBoundingClientRect();
    let moveX = e.clientX - rect.left;
    let moveY = e.clientY - rect.top;

    if (isDraggingImage) {
        imgX = moveX - imgOffsetX;
        imgY = moveY - imgOffsetY;
        loadImage(uploadedImage ? uploadedImage.src : defaultImage);
    }
});


$(canvas).on("mouseup", function () {
    isDraggingImage = false;
});

	
	
if (!isKakeBilder) {
    $(canvas).off("wheel"); // Disable zoom if not product_cat-kake-bilder
} else {
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
}


/* text dragging start */
	
$(canvas).on("mousedown", function (e) {
    let rect = canvas.getBoundingClientRect();
    let clickX = e.clientX - rect.left;
    let clickY = e.clientY - rect.top;

    let textWidth = ctx.measureText($("#custom_text").val()).width;
    let textHeight = 30; // More padding around the text  

    // **Check if clicking on the text first** (this prevents image dragging)
    if (
        clickX >= textX - textWidth / 2 - 10 && clickX <= textX + textWidth / 2 + 10 &&
        clickY >= textY - textHeight / 2 && clickY <= textY + textHeight / 2
    ) {
        isDraggingText = true;
        textOffsetX = clickX - textX;
        textOffsetY = clickY - textY;
        isDraggingImage = false; // **Disable image dragging when text is being moved**
        return;
    }

    // **Only allow image dragging if text is NOT being dragged**
    if (!isDraggingText && isKakeBilder && uploadedImage &&
        clickX >= imgX && clickX <= imgX + imgWidth &&
        clickY >= imgY && clickY <= imgY + imgHeight
    ) {
        isDraggingImage = true;
        imgOffsetX = clickX - imgX;
        imgOffsetY = clickY - imgY;
    }
});

// Mouse Move - Move Image or Text  
$(canvas).on("mousemove", function (e) {
    let rect = canvas.getBoundingClientRect();
    let moveX = e.clientX - rect.left;
    let moveY = e.clientY - rect.top;

    if (isDraggingText) {
        textX = moveX - textOffsetX;
        textY = moveY - textOffsetY;
        loadImage(uploadedImage ? uploadedImage.src : defaultImage);  
        return;
    }

    if (isDraggingImage) {
        imgX = moveX - imgOffsetX;
        imgY = moveY - imgOffsetY;
        loadImage(uploadedImage ? uploadedImage.src : defaultImage);
    }
});

// Mouse Up - Stop Dragging  
$(canvas).on("mouseup", function () {
    isDraggingImage = false;
    isDraggingText = false;
});

	
	
	
/* text dragging end */
	

    // Zoom Feature - Slider
    $("#zoom_slider").on("input", function () {
        if (isZoomDisabled) return;
        imageScale = parseFloat($(this).val());
        loadImage(uploadedImage ? uploadedImage.src : defaultImage);
    });

    // Generate Final Image
    $("#generate_final_image").click(function () {
        let finalImage = canvas.toDataURL("image/png");
        $("#custom_uploaded_image").val(finalImage);
        $("#upload_status").show().text("Bilde generert vellykket!");
    });
	
	// with add to cart button
	function generateFinalImage() {
		let finalImage = canvas.toDataURL("image/png");
		$("#custom_uploaded_image").val(finalImage);
		$("#upload_status").show().text("Bilde generert vellykket!");
	}
	
	// Trigger when clicking "Generate Final Image" button
	$("#generate_final_image").click(function () {
		generateFinalImage();
	});

	// Trigger when clicking "Add to Cart" button
	$(".single_add_to_cart_button").click(function () {
		generateFinalImage();
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

        // Check if clicking text
        let textWidth = ctx.measureText($("#custom_text").val()).width;
        if (touchX >= textX - textWidth / 2 && touchX <= textX + textWidth / 2 &&
            touchY >= textY - 10 && touchY <= textY + 10) {
            isDraggingText = true;
            textOffsetX = touchX - textX;
            textOffsetY = touchY - textY;
            return;
        }

        if (uploadedImage && touchX >= imgX && touchX <= imgX + imgWidth &&
            touchY >= imgY && touchY <= imgY + imgHeight) {
            isDraggingImage = true;
            imgOffsetX = touchX - imgX;
            imgOffsetY = touchY - imgY;
        }
    });

    $(canvas).on("touchmove", function (e) {
        if (isDraggingText || isDraggingImage) {
            let rect = canvas.getBoundingClientRect();
            let touch = e.touches[0];

            if (isDraggingText) {
                textX = touch.clientX - rect.left - textOffsetX;
                textY = touch.clientY - rect.top - textOffsetY;
            }

            if (isDraggingImage) {
                imgX = touch.clientX - rect.left - imgOffsetX;
                imgY = touch.clientY - rect.top - imgOffsetY;
            }

            loadImage(uploadedImage ? uploadedImage.src : defaultImage);
        }
    });

    $(canvas).on("touchend", function () {
        isDraggingImage = false;
        isDraggingText = false;
    });
});

	
/* attrubute qty update */
	
jQuery(document).ready(function($) {
    // Listen for changes in the dropdown
    $('#pa_antall').on('change', function() {
        var selectedValue = $(this).val(); // Get selected option value (15 or 30)
        
        if (selectedValue) {
            $('.qty').val(selectedValue).change(); // Update quantity field
        }
    });
});

	
	
</script>
<?php
}

// dynamic disable zoom feature
add_action('woocommerce_before_single_product', function () {
    global $product;
    if ($product) {
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', array("fields" => "slugs"));
        if (!empty($categories)) {
            $category = esc_attr($categories[0]); // Get the first category
            echo '<script>window.productCategory = "' . $category . '";</script>';
        }
    }
});


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
        $item_data[] = ['name' => 'Egendefinert tekst', 'value' => $cart_item['custom_text']];
    }
    if (!empty($cart_item['custom_image'])) {
        $item_data[] = [
            'name' => 'Lastet opp bilde',
            'value' => '<img src="'.$cart_item['custom_image'].'" width="100">'
        ];
    }
    return $item_data;
}


// Save custom fields to order details
// add_action('woocommerce_checkout_create_order_line_item', 'add_custom_fields_to_order', 10, 4);

// function add_custom_fields_to_order($item, $cart_item_key, $values, $order) {
//     if (isset($values['custom_text'])) {
//         $item->add_meta_data('Custom Text', $values['custom_text']);
//     }
    
//     if (isset($values['custom_image'])) {
//         $item->add_meta_data('Uploaded Image', '<img src="'.$values['custom_image'].'" width="150">');
//     }
// }


// Add custom fields (text & image) to WooCommerce order items
add_action('woocommerce_checkout_create_order_line_item', 'add_custom_fields_to_order', 10, 4);
function add_custom_fields_to_order($item, $cart_item_key, $values, $order) {
    if (isset($values['custom_text'])) {
        $item->add_meta_data('Custom Text', sanitize_text_field($values['custom_text']));
    }
    
    if (isset($values['custom_image'])) {
        $image_url = esc_url($values['custom_image']);
        
        // Save the plain image URL instead of HTML (for better admin display)
        $item->add_meta_data('_custom_uploaded_image', $image_url);
    }
}
// Display uploaded image & download button in WooCommerce Admin Orders
add_action('woocommerce_admin_order_item_headers', 'add_custom_image_column_header');
add_action('woocommerce_admin_order_item_values', 'add_custom_image_column_content', 10, 3);
// Add column header for "Uploaded Image"
function add_custom_image_column_header($order) {
    echo '<th>Uploaded Image</th>';
}
// Display uploaded image in Admin Orders (with Download Button)
function add_custom_image_column_content($product, $item, $order) {
    $image_url = $item->get_meta('_custom_uploaded_image');

    if (!empty($image_url)) {
        echo '<td>';
        echo '<img src="' . esc_url($image_url) . '" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">';
        echo '<br><a href="' . esc_url($image_url) . '" download class="button button-primary">Download</a>';
        echo '</td>';
    } else {
        echo '<td>—</td>'; // If no image exists
    }
}





// Display the uploaded image with a download button in WooCommerce Admin Order Dashboard
add_action('woocommerce_admin_order_data_after_order_details', 'display_uploaded_image_in_admin_order_meta');
function display_uploaded_image_in_admin_order_meta($order) {
    $image_url = $order->get_meta('_custom_uploaded_image');

    if (!empty($image_url)) {
        echo '<p><strong>Custom Uploaded Image:</strong></p>';
        echo '<img id="order_uploaded_image" src="' . esc_url($image_url) . '" style="width: 80px; height: 80px; border-radius: 10px; object-fit: cover; margin-bottom: 10px;">';

        // JavaScript-based download button
        echo '<p><button id="download_uploaded_image" class="button button-primary">Download Image</button></p>';

        echo '<script>
            document.getElementById("download_uploaded_image").addEventListener("click", function() {
                let imageElement = document.getElementById("order_uploaded_image");
                let imageURL = imageElement.src;
                let link = document.createElement("a");
                link.href = imageURL;
                link.download = "custom_uploaded_image.png";
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        </script>';
    } else {
        echo '<p style="color: gray;">No custom image uploaded.</p>';
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


