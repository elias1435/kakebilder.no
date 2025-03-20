<?php

// redirect after add to cart
function redirect_to_cart_after_add_to_cart($url) {
    return wc_get_cart_url();
}
add_filter('woocommerce_add_to_cart_redirect', 'redirect_to_cart_after_add_to_cart');



/* start quantity function */

// set default quantity for selected categories
add_filter('woocommerce_quantity_input_args', 'set_default_quantity', 10, 2);
function set_default_quantity($args, $product) {
    // Check if the product belongs to the allowed categories
    if (has_term(['kake-bilder', 'kake-bilder-1'], 'product_cat', $product->get_id())) {
        $args['input_value'] = 15; // Set default quantity to 15
    }
    return $args;
}

// chanage qty when update qty dropdown
add_action('wp_footer', 'sync_dropdown_with_quantity');
function sync_dropdown_with_quantity() { ?>
    <script type="text/javascript">
    jQuery(document).ready(function ($) {
        //console.log("Custom dropdown-quantity script is running...");

        let dropdown = $("#pa_antall");
        let quantityField = $("input.qty");

        if (dropdown.length > 0) {
            //console.log("Dropdown found:", dropdown);
        } else {
            //console.log("Dropdown NOT found! Check ID.");
        }

        if (quantityField.length > 0) {
            //console.log("Quantity field found:", quantityField);
        } else {
            //console.log("Quantity field NOT found! Check class.");
        }

        dropdown.on("change", function () {
            let selectedValue = $(this).val();
            //console.log("Dropdown selected value:", selectedValue);

            if (selectedValue) {
                quantityField.val(selectedValue).trigger("change"); // Update quantity input field
                //console.log("Quantity field after change:", quantityField.val());
            } else {
                //console.log("No value selected from dropdown.");
            }
        });
    });
    </script>
<?php }



/* end quantity function */


/* start update cart quantity on change */

add_action('wp_footer', 'ajax_update_cart_quantity');
function ajax_update_cart_quantity() {
    if (is_cart()) : ?>
        <script type="text/javascript">
		jQuery(function ($) {
			$(document).on('change', 'input.qty', function () {
				let qty = $(this).val();
				let cartItemKey = $(this).attr("name").match(/\[(.*?)\]/)[1];

				$.ajax({
					type: "POST",
					url: "<?php echo admin_url('admin-ajax.php'); ?>",
					data: {
						action: "update_cart_quantity",
						cart_key: cartItemKey,
						quantity: qty
					},
					success: function (response) {
						$(document.body).trigger("wc_update_cart");
						$(document.body).trigger("wc_fragment_refresh");
					}
				});
			});
		});
        </script>
    <?php endif;
}


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


/* end update cart quantity on change */


add_action('woocommerce_before_add_to_cart_button', 'add_custom_image_text_fields');

function add_custom_image_text_fields() {
    global $product;
    
    $product_id = $product->get_id();

	$featured_image = get_the_post_thumbnail_url($product->get_id(), 'full');
    
    ?>

    <div class="custom-upload-container">
<label for="custom_text">Skriv inn din egendefinerte tekst</label>
<input type="text" id="custom_text" name="custom_text" style="width:100%; padding:5px; margin-top:5px;">

<!-- New Color Picker Input -->
		
<label style="margin-top: 10px;" for="text_color">Velg tekstfarge</label>
<input type="color" id="text_color" name="text_color" value="#000000" style="width:150px; margin-top:5px;margin-bottom:15px;">

<!-- Hidden Input to Store Selected Color -->
<input type="hidden" name="selected_text_color" id="selected_text_color" value="#000000">

		
		
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
		
		<div class="bt-space10 zoom-handler">
			<label for="zoom_slider">Zoom</label>
			<input type="range" id="zoom_slider" min="0.5" max="2" step="0.1" value="1">	
		</div>

        <!---<button type="button" id="generate_final_image" class="button">Generer endelig bilde</button>-->
        <p id="upload_status">Bilde generert vellykket!</p>

        <input type="hidden" name="uploaded-original-image" id="uploaded-original-image" value="">
        <input type="hidden" name="custom_uploaded_image" id="custom_uploaded_image" value="" />
    </div>



<script>
jQuery(document).ready(function ($) {
    let canvas = document.getElementById("canvas");
    let ctx = canvas.getContext("2d");

    let textX = canvas.width / 2, textY = canvas.height / 2;
    let isDraggingText = false, textOffsetX, textOffsetY;

    let uploadedImage = null;
    let imageScale = 1;
    let imgX = 0, imgY = 0, imgWidth = canvas.width, imgHeight = canvas.height;
    let isDraggingImage = false;
    let imgOffsetX = 0, imgOffsetY = 0;

    let defaultImage = $("#featured_image").val();

    function loadImage(src) {
        let img = new Image();
        img.crossOrigin = "Anonymous";
        img.src = src;

        img.onload = function () {
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            let scale = Math.max(canvas.width / img.width, canvas.height / img.height);
            imgWidth = img.width * scale * imageScale;
            imgHeight = img.height * scale * imageScale;

            ctx.drawImage(img, imgX, imgY, imgWidth, imgHeight);
            drawText();

            uploadedImage = img;
        };
    }

    function drawText() {
        let textValue = $("#custom_text").val();
        let textColor = $("#text_color").val(); // Get the selected text color

        ctx.font = "bold 24px Arial";
        ctx.fillStyle = textColor;
        ctx.textAlign = "center";
        ctx.fillText(textValue, textX, textY);
    }

    loadImage(defaultImage);

    $("#custom_text").on("input", function () {
        loadImage(uploadedImage ? uploadedImage.src : defaultImage);
    });

    $("#text_color").on("input", function () {
        $("#selected_text_color").val($(this).val());
        loadImage(uploadedImage ? uploadedImage.src : defaultImage);
    });

    $("#image_upload").on("change", function (event) {
        let file = event.target.files[0];
        if (!file) return;

        let reader = new FileReader();
        reader.onload = function (e) {
            uploadedImage = new Image();
            uploadedImage.crossOrigin = "Anonymous";
            uploadedImage.src = e.target.result;

            uploadedImage.onload = function () {
                imgX = 0;
                imgY = 0;
                loadImage(uploadedImage.src);
            };

            $("#uploaded-original-image").val(e.target.result);
        };
        reader.readAsDataURL(file);
    });

    $(canvas).on("mousedown", function (e) {
        let rect = canvas.getBoundingClientRect();
        let clickX = e.clientX - rect.left;
        let clickY = e.clientY - rect.top;

        let textWidth = ctx.measureText($("#custom_text").val()).width;
        let textHeight = 30;

        if (
            clickX >= textX - textWidth / 2 - 10 && clickX <= textX + textWidth / 2 + 10 &&
            clickY >= textY - textHeight / 2 && clickY <= textY + textHeight / 2
        ) {
            isDraggingText = true;
            textOffsetX = clickX - textX;
            textOffsetY = clickY - textY;
            return;
        }

        if (uploadedImage && clickX >= imgX && clickX <= imgX + imgWidth &&
            clickY >= imgY && clickY <= imgY + imgHeight) {
            isDraggingImage = true;
            imgOffsetX = clickX - imgX;
            imgOffsetY = clickY - imgY;
        }
    });

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

    $(canvas).on("mouseup", function () {
        isDraggingImage = false;
        isDraggingText = false;
    });

    // **Handle Mouse Wheel Zooming**
    $(canvas).on("wheel", function (event) {
        event.preventDefault(); // Prevent page scrolling

        let zoomFactor = 0.1;
        if (event.originalEvent.deltaY < 0) {
            imageScale += zoomFactor; // Zoom In
        } else {
            imageScale = Math.max(0.5, imageScale - zoomFactor); // Zoom Out (Min limit 0.5)
        }
        loadImage(uploadedImage ? uploadedImage.src : defaultImage);
    });

    // Handle zoom slider
    $("#zoom_slider").on("input", function () {
        imageScale = parseFloat($(this).val());
        loadImage(uploadedImage ? uploadedImage.src : defaultImage);
    });

    function generateFinalImage() {
        let finalImage = canvas.toDataURL("image/png");
        $("#custom_uploaded_image").val(finalImage);
        $("#upload_status").show().text("Bilde generert vellykket!");
    }

    $("#generate_final_image").click(function () {
        generateFinalImage();
    });

    $(".single_add_to_cart_button").click(function () {
        generateFinalImage();
    });

    let textInput = $("#custom_text");
    let generateButton = $("#generate_final_image");

    function checkText() {
        let textValue = textInput.val().trim();
        generateButton.prop("disabled", textValue === "");
    }

    checkText();
    textInput.on("input", checkText);

    // Handle touch events for mobile users
    $(canvas).on("touchstart", function (e) {
        let rect = canvas.getBoundingClientRect();
        let touch = e.touches[0];
        let touchX = touch.clientX - rect.left;
        let touchY = touch.clientY - rect.top;

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
</script>


<?php
}


add_action('woocommerce_before_single_product', function () {
    global $product;
    if ($product) {
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', array("fields" => "slugs"));
        if (!empty($categories)) {
            $category = esc_attr($categories[0]);
            echo '<script>window.productCategory = "' . $category . '";</script>';
        }
    }
});


add_filter('woocommerce_add_cart_item_data', 'save_custom_image_to_server', 10, 2);
function save_custom_image_to_server($cart_item_data, $product_id) {
    $upload_dir = wp_upload_dir();

    if (!empty($_POST['custom_uploaded_image'])) {
        $base64_image = $_POST['custom_uploaded_image'];
        $image_parts = explode(";base64,", $base64_image);

        if (count($image_parts) == 2) {
            $image_base64 = base64_decode($image_parts[1]);

            $image_name = 'custom_product_' . uniqid() . '.png';
            $file_path = $upload_dir['path'] . '/' . $image_name;
            $file_url = $upload_dir['url'] . '/' . $image_name;

            file_put_contents($file_path, $image_base64);

            $cart_item_data['custom_image'] = esc_url($file_url);
            WC()->session->set('custom_uploaded_image', $file_url);
        }
    }

    if (!empty($_POST['uploaded-original-image'])) {
        $base64_image = $_POST['uploaded-original-image'];
        $image_parts = explode(";base64,", $base64_image);

        if (count($image_parts) == 2) {
            $image_base64 = base64_decode($image_parts[1]);

            $image_name = 'uploaded_original_' . uniqid() . '.png';
            $file_path = $upload_dir['path'] . '/' . $image_name;
            $file_url = $upload_dir['url'] . '/' . $image_name;

            file_put_contents($file_path, $image_base64);
			
            $cart_item_data['uploaded_original_image'] = esc_url($file_url);
            WC()->session->set('uploaded_original_image', $file_url);
        }
    }

    if (!empty($_POST['custom_text'])) {
        $cart_item_data['custom_text'] = sanitize_text_field($_POST['custom_text']);
        WC()->session->set('custom_text', $_POST['custom_text']);
    }

    return $cart_item_data;
}

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

add_filter('woocommerce_cart_item_name', 'display_uploaded_original_image_in_cart', 10, 3);
function display_uploaded_original_image_in_cart($product_name, $cart_item, $cart_item_key) {
    if (!empty($cart_item['uploaded_original_image'])) {
        $product_name .= '<p class="uploaded-original-image"><strong>Lastet opp originalbilde:</strong><br>';
        $product_name .= '<img src="'.esc_url($cart_item['uploaded_original_image']).'" style="max-width:100px; height:auto; border:1px solid #ddd; padding:1px;"></p>';
    }
    return $product_name;
}

add_action('woocommerce_checkout_create_order_line_item', 'save_uploaded_images_to_order_meta', 10, 4);
function save_uploaded_images_to_order_meta($item, $cart_item_key, $values, $order) {
    if (!empty($values['custom_text'])) {
        $item->add_meta_data('Egendefinert tekst', sanitize_text_field($values['custom_text']));
    }

    if (!empty($values['custom_image'])) {
        $item->add_meta_data('Lastet opp bilde', esc_url($values['custom_image']));
    }

    if (!empty($values['uploaded_original_image'])) {
        $item->add_meta_data('Lastet opp originalbilde', esc_url($values['uploaded_original_image']));
    }
}


add_action('woocommerce_admin_order_item_values', 'display_uploaded_images_in_admin_order', 10, 3);
function display_uploaded_images_in_admin_order($column, $item, $order) {
    // Ensure the correct column is targeted (Product column)
    if ($column !== 'name') {
        return;
    }

    $custom_text = $item->get_meta('Custom Text', true);
    $custom_image = $item->get_meta('Uploaded Image', true);
    $uploaded_original_image = $item->get_meta('Uploaded Original Image', true);

    if (!empty($custom_text)) {
        echo '<p><strong>Tilpasset tekst:</strong> ' . esc_html($custom_text) . '</p>';
    }

    if (!empty($custom_image)) {
        echo '<p><strong>Generert bilde:</strong><br>';
        echo '<img src="' . esc_url($custom_image) . '" style="max-width:100px; height:auto; border:1px solid #ddd; padding:2px;"></p>';
    }

    if (!empty($uploaded_original_image)) {
        echo '<p><strong>Lastet opp originalbilde:</strong><br>';
        echo '<img src="' . esc_url($uploaded_original_image) . '" style="max-width:100px; height:auto; border:1px solid #ddd; padding:2px;"><br>';
        echo '<a href="' . esc_url($uploaded_original_image) . '" download class="button button-primary">Download Original Image</a></p>';
    }
}

add_action('woocommerce_email_order_meta', 'attach_custom_image_to_emails', 10, 3);

function attach_custom_image_to_emails($order, $sent_to_admin, $plain_text) {
    $image_url = $order->get_meta('_custom_uploaded_image');

    if (!empty($image_url)) {
        echo '<p><strong>Lastet opp bilde:</strong></p>';
        echo '<img src="' . esc_url($image_url) . '" style="width: 50px; height: 50px; border-radius: 100%; object-fit: cover;">';
    }
}


add_action('woocommerce_email_order_details', 'add_custom_fields_to_order_email', 20, 4);

function add_custom_fields_to_order_email($order, $sent_to_admin, $plain_text, $email) {
    echo '<h2>Custom Order Details</h2>';
    
    foreach ($order->get_items() as $item) {
        $custom_text = $item->get_meta('Custom Text', true);
        $uploaded_image = $item->get_meta('Uploaded Image', true);

        if (!empty($custom_text)) {
            echo '<p><strong>Custom Text:</strong> ' . esc_html($custom_text) . '</p>';
        }

        if (!empty($uploaded_image)) {
            echo '<p><strong>Uploaded Image:</strong></p>';
            echo '<img src="' . esc_url($uploaded_image) . '" style="width: 50px; height: 50px; border-radius: 100%; object-fit: cover;">';
        }
    }
}

