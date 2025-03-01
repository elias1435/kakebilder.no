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


function redirect_to_cart_after_add_to_cart($url) {
    return wc_get_cart_url();
}
add_filter('woocommerce_add_to_cart_redirect', 'redirect_to_cart_after_add_to_cart');



add_filter('woocommerce_quantity_input_args', 'set_default_quantity', 10, 2);
function set_default_quantity($args, $product) {
    if (is_product()) {
        $args['input_value'] = 15;
    }
    return $args;
}


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



add_action('woocommerce_before_add_to_cart_button', 'add_custom_image_text_fields');

function add_custom_image_text_fields() {
    global $product;
    
    $product_id = $product->get_id();

	$featured_image = get_the_post_thumbnail_url($product->get_id(), 'full');
    
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
		
		<div class="bt-space10 zoom-handler">
			<label for="zoom_slider">Zoom</label>
			<input type="range" id="zoom_slider" min="0.5" max="2" step="0.1" value="1">	
		</div>

        <!--<button type="button" id="generate_final_image" class="button">Generer endelig bilde</button>-->
        <p id="upload_status">Bilde generert vellykket!</p>
        <input type="hidden" name="custom_uploaded_image" id="custom_uploaded_image" value="" />
    </div>





<script>

jQuery(document).ready(function ($) {
    let canvas = document.getElementById("canvas");
	canvas.width = 300;
	canvas.height = 300;
    let ctx = canvas.getContext("2d");

    let textX = canvas.width / 2, textY = canvas.height / 2;
    let isDraggingText = false, textOffsetX, textOffsetY;

    let uploadedImage = null;
    let imageScale = 1;
    let imgX = 0, imgY = 0, imgWidth = canvas.width, imgHeight = canvas.height;
    let isDraggingImage = false;
    let imgOffsetX = 0, imgOffsetY = 0;


    let defaultImage = $("#featured_image").val();


    let isKakeBilder = $("div").hasClass("product_cat-kake-bilder");
    let productCategory = $("#product_category").val();
    let isZoomDisabled = (productCategory === "temabilder" || productCategory === "product_cat-temabilder");

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
        ctx.font = "bold 24px Arial";
        ctx.fillStyle = "black";
        ctx.textAlign = "center";
        ctx.fillText(textValue, textX, textY);
    }

    loadImage(defaultImage);

    $("#custom_text").on("input", function () {
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
		$(canvas).off("wheel");
	} else {
		$(canvas).on("wheel", function (event) {
			event.preventDefault();
			let zoomFactor = 0.1;
			if (event.originalEvent.deltaY < 0) {
				imageScale += zoomFactor;
			} else {
				imageScale = Math.max(0.5, imageScale - zoomFactor);
			}
			loadImage(uploadedImage ? uploadedImage.src : defaultImage);
		});
	}

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
			isDraggingImage = false;
			return;
		}

		if (!isDraggingText && isKakeBilder && uploadedImage &&
			clickX >= imgX && clickX <= imgX + imgWidth &&
			clickY >= imgY && clickY <= imgY + imgHeight
		) {
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

    $("#zoom_slider").on("input", function () {
        if (isZoomDisabled) return;
        imageScale = parseFloat($(this).val());
        loadImage(uploadedImage ? uploadedImage.src : defaultImage);
    });

    $("#generate_final_image").click(function () {
        let finalImage = canvas.toDataURL("image/png");
        $("#custom_uploaded_image").val(finalImage);
        $("#upload_status").show().text("Bilde generert vellykket!");
    });
	
	function generateFinalImage() {
		let finalImage = canvas.toDataURL("image/png");
		$("#custom_uploaded_image").val(finalImage);
		$("#upload_status").show().text("Bilde generert vellykket!");
	}
	

	function generateFinalImage() {
		let exportCanvas = document.createElement("canvas");
		let exportCtx = exportCanvas.getContext("2d");

		// Set export canvas to 1024x1024
		exportCanvas.width = 1024;
		exportCanvas.height = 1024;

		// Draw background image scaled up
		if (uploadedImage) {
			let scale = Math.max(exportCanvas.width / uploadedImage.width, exportCanvas.height / uploadedImage.height);
			let imgWidth = uploadedImage.width * scale;
			let imgHeight = uploadedImage.height * scale;
			let imgX = (exportCanvas.width - imgWidth) / 2;
			let imgY = (exportCanvas.height - imgHeight) / 2;

			exportCtx.drawImage(uploadedImage, imgX, imgY, imgWidth, imgHeight);
		}

		// Draw text on the high-res canvas
		let textValue = $("#custom_text").val();
		exportCtx.font = "bold 72px Arial"; // Scale text for higher resolution
		exportCtx.fillStyle = "black";
		exportCtx.textAlign = "center";
		exportCtx.fillText(textValue, exportCanvas.width / 2, exportCanvas.height / 2);

		// Convert to image and store in hidden input
		let finalImage = exportCanvas.toDataURL("image/png");
		$("#custom_uploaded_image").val(finalImage);
		$("#upload_status").show().text("Bilde generert vellykket!");
	}

	
	
	
	
	
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


jQuery(document).ready(function($) {

    $('#pa_antall').on('change', function() {
        var selectedValue = $(this).val();
        
        if (selectedValue) {
            $('.qty').val(selectedValue).change();
        }
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
    if (!empty($_POST['custom_uploaded_image'])) {
        $base64_image = $_POST['custom_uploaded_image'];

        $image_parts = explode(";base64,", $base64_image);
        if (count($image_parts) == 2) {
            $image_base64 = base64_decode($image_parts[1]);
            $upload_dir = wp_upload_dir();
            

            $image_name = 'custom_product_' . uniqid() . '.png';
            $file_path = $upload_dir['path'] . '/' . $image_name;
            $file_url = $upload_dir['url'] . '/' . $image_name;


            file_put_contents($file_path, $image_base64);


            $cart_item_data['custom_image'] = esc_url($file_url);


            WC()->session->set('custom_uploaded_image', $file_url);
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

// TY
add_action('woocommerce_checkout_create_order_line_item', 'save_custom_fields_to_order_items', 10, 4);
function save_custom_fields_to_order_items($item, $cart_item_key, $values, $order) {
    if (!empty($values['custom_text'])) {
        $item->add_meta_data('Custom Text', sanitize_text_field($values['custom_text']));
    }

    if (!empty($values['custom_image'])) {
        $item->add_meta_data('Uploaded Image', esc_url($values['custom_image']));
    }
}
add_action('woocommerce_thankyou', 'display_custom_fields_on_thank_you_page', 20);
function display_custom_fields_on_thank_you_page($order_id) {
    $order = wc_get_order($order_id);
    
    echo '<h2>Custom Order Details</h2>';
    
    foreach ($order->get_items() as $item) {
        $custom_text = $item->get_meta('Custom Text', true);
        $uploaded_image = $item->get_meta('Uploaded Image', true);

        if (!empty($custom_text)) {
            echo '<p><strong>Custom Text:</strong> ' . esc_html($custom_text) . '</p>';
        }
        
        if (!empty($uploaded_image)) {
            echo '<p><strong>Uploaded Image:</strong></p>';
            echo '<img src="' . esc_url($uploaded_image) . '" style="max-width:200px; height:auto; border:1px solid #ddd; padding:5px;">';
        }
    }
}




add_action('woocommerce_admin_order_item_headers', 'add_custom_image_column_header');
add_action('woocommerce_admin_order_item_values', 'add_custom_image_column_content', 10, 3);

function add_custom_image_column_header($order) {
    echo '<th>Lastet opp bilde</th>';
}

function add_custom_image_column_content($product, $item, $order) {
    $image_url = $item->get_meta('_custom_uploaded_image');

    if (!empty($image_url)) {
        echo '<td>';
        echo '<img src="' . esc_url($image_url) . '" style="width: 100px; height: 100px; border-radius: 100%; object-fit: cover;">';
        echo '<br><a href="' . esc_url($image_url) . '" download class="button button-primary">Download</a>';
        echo '</td>';
    } else {
        echo '<td>—</td>';
    }
}



add_action('woocommerce_admin_order_data_after_order_details', 'display_uploaded_image_in_admin_order_meta');
function display_uploaded_image_in_admin_order_meta($order) {
    $image_url = $order->get_meta('_custom_uploaded_image');

    if (!empty($image_url)) {
        echo '<p><strong>Custom Uploaded Image:</strong></p>';
        echo '<img id="order_uploaded_image" src="' . esc_url($image_url) . '" style="width: 50px; height: 50px; border-radius: 100%; object-fit: cover;">';

        echo '<p><button id="download_uploaded_image" class="button button-primary">Lastet opp bilde</button></p>';

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


