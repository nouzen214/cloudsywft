import { __, sprintf } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { Button, SelectControl, Spinner, ToggleControl, PanelBody, Placeholder } from '@wordpress/components';
import { useState, useCallback, useRef, useEffect } from '@wordpress/element';

export default function Edit( { attributes, setAttributes } ) {
	const { images, speed, imageHeight, gap, pauseOnHover, direction, maxWidth } = attributes;
	const [ isLoading, setIsLoading ] = useState( false );
	const [ a11yMessage, setA11yMessage ] = useState( '' );
	const [ activeThumbIndex, setActiveThumbIndex ] = useState( 0 );
	const [ inputMode, setInputMode ] = useState( 'mouse' );
	const blockProps = useBlockProps();

	// Drag state using refs to avoid stale closures in pointer event handlers
	const dragState = useRef( { active: false, index: null } );
	const [ dragIndex, setDragIndex ] = useState( null );
	const gridRef = useRef( null );
	const pendingFocusIndexRef = useRef( null );

	const onSelectImages = ( selectedImages ) => {
		setIsLoading( true );
		const processedImages = selectedImages.map( ( image ) => ( {
			id: image.id,
			url: image.url,
			alt: image.alt || '',
		} ) );
		setAttributes( { images: processedImages } );
		setActiveThumbIndex( 0 );
		setIsLoading( false );
	};

	useEffect( () => {
		if ( images.length === 0 ) {
			setActiveThumbIndex( 0 );
			return;
		}
		if ( activeThumbIndex > images.length - 1 ) {
			setActiveThumbIndex( images.length - 1 );
		}
	}, [ images.length, activeThumbIndex ] );

	useEffect( () => {
		if ( pendingFocusIndexRef.current === null ) {
			return;
		}

		const nextIndex = pendingFocusIndexRef.current;
		pendingFocusIndexRef.current = null;

		if ( images.length === 0 ) {
			return;
		}

		focusThumbByIndex( nextIndex );
	}, [ images.length ] );

	const moveImage = ( fromIndex, toIndex ) => {
		if ( toIndex < 0 || toIndex >= images.length || fromIndex === toIndex ) {
			return false;
		}
		const newImages = [ ...images ];
		const [ movedImage ] = newImages.splice( fromIndex, 1 );
		newImages.splice( toIndex, 0, movedImage );
		setAttributes( { images: newImages } );
		return true;
	};

	const removeImage = ( index ) => {
		const newImages = images.filter( ( _, i ) => i !== index );
		const nextIndex = newImages.length === 0
			? null
			: Math.min( index, newImages.length - 1 );

		pendingFocusIndexRef.current = nextIndex;
		setAttributes( { images: newImages } );

		if ( nextIndex === null ) {
			setActiveThumbIndex( 0 );
			setA11yMessage( __( 'Image removed.', 'marqueeflow' ) );
		} else {
			setActiveThumbIndex( nextIndex );
			setA11yMessage(
				sprintf(
					/* translators: 1: new focused position, 2: total number of images */
					__( 'Image removed. Focus moved to image %1$d of %2$d.', 'marqueeflow' ),
					nextIndex + 1,
					newImages.length
				)
			);
		}
	};

	// Find which thumbnail the pointer is over
	const getThumbIndexAtPoint = ( x, y ) => {
		if ( ! gridRef.current ) {
			return null;
		}
		const thumbs = gridRef.current.querySelectorAll( '.marqueeflow-editor__thumb' );
		for ( let i = 0; i < thumbs.length; i++ ) {
			const rect = thumbs[ i ].getBoundingClientRect();
			if ( x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom ) {
				return i;
			}
		}
		return null;
	};

	// Pointer-based drag-and-drop with live reorder
	const handlePointerDown = useCallback( ( e, index ) => {
		if ( e.target.closest( '.marqueeflow-editor__remove' ) ) {
			return;
		}
		e.stopPropagation();
		setInputMode( 'mouse' );
		setActiveThumbIndex( index );
		dragState.current = { active: true, index };
		setDragIndex( index );
		e.currentTarget.setPointerCapture( e.pointerId );
	}, [] );

	const handlePointerMove = useCallback( ( e ) => {
		if ( ! dragState.current.active ) {
			return;
		}
		const overIndex = getThumbIndexAtPoint( e.clientX, e.clientY );
		const currentIndex = dragState.current.index;
		if ( overIndex !== null && overIndex !== currentIndex ) {
			if ( moveImage( currentIndex, overIndex ) ) {
				dragState.current.index = overIndex;
				setDragIndex( overIndex );
			}
		}
	}, [ images ] );

	const handlePointerUp = useCallback( () => {
		dragState.current = { active: false, index: null };
		setDragIndex( null );
	}, [] );

	const focusThumbByIndex = ( index ) => {
		requestAnimationFrame( () => {
			const targetThumb = gridRef.current?.querySelector( `[data-thumb-index="${ index }"]` );
			if ( targetThumb ) {
				targetThumb.focus();
			}
		} );
	};

	const handleGridKeyDownCapture = ( event ) => {
		const { key, shiftKey } = event;
		if ( key !== 'ArrowLeft' && key !== 'ArrowUp' && key !== 'ArrowRight' && key !== 'ArrowDown' ) {
			return;
		}
		setInputMode( 'keyboard' );

		// Do not hijack arrow keys while typing in popover inputs.
		if ( event.target.closest( 'input, textarea, [contenteditable="true"]' ) ) {
			return;
		}

		const thumbElement = event.target.closest( '.marqueeflow-editor__thumb' );
		if ( ! thumbElement ) {
			return;
		}

		const currentIndex = Number( thumbElement.dataset.thumbIndex );
		if ( Number.isNaN( currentIndex ) ) {
			return;
		}

		const isHorizontal = key === 'ArrowLeft' || key === 'ArrowRight';
		const isVertical = key === 'ArrowUp' || key === 'ArrowDown';
		const focusedRemove = !!event.target.closest( '.marqueeflow-editor__remove' );

		if ( isHorizontal ) {
			let targetIndex = currentIndex;
			if ( key === 'ArrowLeft' ) {
				targetIndex = currentIndex - 1;
			} else if ( key === 'ArrowRight' ) {
				targetIndex = currentIndex + 1;
			}

			event.preventDefault();
			event.stopPropagation();

			if ( shiftKey ) {
				if ( moveImage( currentIndex, targetIndex ) ) {
					setActiveThumbIndex( targetIndex );
					setA11yMessage(
						sprintf(
							/* translators: 1: new position, 2: total number of images */
							__( 'Image moved to position %1$d of %2$d.', 'marqueeflow' ),
							targetIndex + 1,
							images.length
						)
					);
					focusThumbByIndex( targetIndex );
				}
				return;
			}

			if ( targetIndex < 0 || targetIndex >= images.length ) {
				return;
			}
			setActiveThumbIndex( targetIndex );
			focusThumbByIndex( targetIndex );
			return;
		}

		if ( isVertical ) {
			event.preventDefault();
			event.stopPropagation();
			setActiveThumbIndex( currentIndex );

			// Up/Down cycles: thumb -> remove -> thumb.
			if ( focusedRemove ) {
				focusThumbByIndex( currentIndex );
			} else {
				requestAnimationFrame( () => {
					const removeBtn = gridRef.current?.querySelector(
						`[data-thumb-index="${ currentIndex }"] .marqueeflow-editor__remove`
					);
					if ( removeBtn ) {
						removeBtn.focus();
					}
				} );
			}
		}
	};

	const handleEditImagesKeyDown = ( event ) => {
		if ( event.key === 'Tab' && ! event.shiftKey ) {
			event.preventDefault();
			setInputMode( 'keyboard' );
			focusThumbByIndex( activeThumbIndex );
		}
	};

	const heightValues = { small: 40, medium: 60, large: 80 };
	const previewHeightPx = heightValues[ imageHeight ] || 60;

	// Build marquee preview class names (mirrors PHP render logic)
	const getPreviewClasses = () => {
		const classes = [ 'marqueeflow', `marqueeflow--${ speed }` ];
		classes.push( `marqueeflow--height-${ imageHeight }` );
		classes.push( `marqueeflow--gap-${ gap }` );
		if ( direction === 'rtl' ) {
			classes.push( 'marqueeflow--rtl' );
		}
		if ( pauseOnHover ) {
			classes.push( 'marqueeflow--pause-hover' );
		}
		classes.push( `marqueeflow--max-width-${ maxWidth }` );
		return classes.join( ' ' );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Animation', 'marqueeflow' ) } initialOpen={ true }>
					<SelectControl
						label={ __( 'Speed', 'marqueeflow' ) }
						value={ speed }
						options={ [
							{ label: __( 'Slow', 'marqueeflow' ), value: 'slow' },
							{ label: __( 'Normal', 'marqueeflow' ), value: 'normal' },
							{ label: __( 'Fast', 'marqueeflow' ), value: 'fast' },
						] }
						onChange={ ( newSpeed ) => setAttributes( { speed: newSpeed } ) }
					/>

					<SelectControl
						label={ __( 'Direction', 'marqueeflow' ) }
						value={ direction }
						options={ [
							{ label: __( 'Left to Right', 'marqueeflow' ), value: 'ltr' },
							{ label: __( 'Right to Left', 'marqueeflow' ), value: 'rtl' },
						] }
						onChange={ ( newDirection ) => setAttributes( { direction: newDirection } ) }
					/>

					<SelectControl
						label={ __( 'Gap Between Images', 'marqueeflow' ) }
						value={ gap }
						options={ [
							{ label: __( 'Normal', 'marqueeflow' ), value: 'normal' },
							{ label: __( 'Spacious', 'marqueeflow' ), value: 'spacious' },
						] }
						onChange={ ( newGap ) => setAttributes( { gap: newGap } ) }
					/>

					<ToggleControl
						label={ __( 'Pause on Hover', 'marqueeflow' ) }
						checked={ pauseOnHover }
						onChange={ ( newValue ) => setAttributes( { pauseOnHover: newValue } ) }
						help={ __( 'Stop animation when user hovers over the slider', 'marqueeflow' ) }
					/>

				</PanelBody>

				<PanelBody title={ __( 'Images', 'marqueeflow' ) } initialOpen={ false }>
					<SelectControl
						label={ __( 'Image Height', 'marqueeflow' ) }
						value={ imageHeight }
						options={ [
							{ label: __( 'Small (40px)', 'marqueeflow' ), value: 'small' },
							{ label: __( 'Medium (60px)', 'marqueeflow' ), value: 'medium' },
							{ label: __( 'Large (80px)', 'marqueeflow' ), value: 'large' },
						] }
						onChange={ ( newHeight ) => setAttributes( { imageHeight: newHeight } ) }
						help={ __( 'Images scale proportionally to the set height', 'marqueeflow' ) }
					/>

					<SelectControl
						label={ __( 'Max Image Width', 'marqueeflow' ) }
						value={ maxWidth }
						options={ [
							{ label: __( 'Narrow (3× height)', 'marqueeflow' ), value: 'narrow' },
							{ label: __( 'Normal (4× height)', 'marqueeflow' ), value: 'normal' },
							{ label: __( 'Medium (5× height)', 'marqueeflow' ), value: 'medium' },
							{ label: __( 'Wide (6× height)', 'marqueeflow' ), value: 'wide' },
							{ label: __( 'None (no limit)', 'marqueeflow' ), value: 'none' },
						] }
						onChange={ ( newValue ) => setAttributes( { maxWidth: newValue } ) }
						help={ __( 'Limits how wide a single image can grow. Prevents long horizontal images from dominating the strip.', 'marqueeflow' ) }
					/>

				</PanelBody>

			</InspectorControls>

			<div { ...blockProps }>
				{ /* Live marquee preview */ }
				{ images.length > 0 && (
					<div className="marqueeflow-editor__preview">
						<span className="marqueeflow-editor__preview-label">
							{ __( 'Preview', 'marqueeflow' ) }
						</span>
						<div
							className={ getPreviewClasses() }
							style={ {
								'--marqueeflow-total-images': images.length,
								'--marqueeflow-height': `${ previewHeightPx }px`,
							} }
						>
							{ [ 'a', 'b', 'c' ].map( ( setKey ) => (
								<ul key={ setKey } className="marqueeflow__set">
									{ images.map( ( image, index ) => (
										<li key={ `${ setKey }-${ index }` } className="marqueeflow__item">
											<img
												src={ image.url }
												alt={ image.alt }
											/>
										</li>
									) ) }
								</ul>
							) ) }
						</div>
					</div>
				) }

				{ /* Empty state placeholder */ }
				{ images.length === 0 && (
					<MediaUploadCheck>
						<MediaUpload
							onSelect={ onSelectImages }
							allowedTypes={ [ 'image' ] }
							multiple
							value={ [] }
							render={ ( { open } ) => (
								<Placeholder
									icon="format-gallery"
									label={ __( 'MarqueeFlow', 'marqueeflow' ) }
									instructions={ __( 'Add images to create a continuously scrolling marquee.', 'marqueeflow' ) }
								>
									<Button variant="primary" onClick={ open }>
										{ __( 'Select Images', 'marqueeflow' ) }
									</Button>
								</Placeholder>
							) }
						/>
					</MediaUploadCheck>
				) }

				{ /* Image management area */ }
				{ images.length > 0 && (
					<div
						className="marqueeflow-editor__management"
						onKeyDownCapture={ () => setInputMode( 'keyboard' ) }
						onPointerDownCapture={ () => setInputMode( 'mouse' ) }
					>
						{ isLoading && <Spinner /> }

						<MediaUploadCheck>
							<MediaUpload
								onSelect={ onSelectImages }
								allowedTypes={ [ 'image' ] }
								multiple
								value={ images.map( ( img ) => img.id ) }
								render={ ( { open } ) => (
									<Button
										onClick={ open }
										onKeyDown={ handleEditImagesKeyDown }
										variant="primary"
										style={ { marginBottom: '12px' } }
									>
										{ __( 'Edit Images', 'marqueeflow' ) }
									</Button>
								) }
							/>
						</MediaUploadCheck>

						{ images.length < 6 && (
							<p className="marqueeflow-editor__hint">
								{ __( 'For a smooth scrolling effect, we recommend at least 6 images.', 'marqueeflow' ) }
							</p>
						) }

							<p style={ { fontSize: '0.875rem', margin: '0 0 4px' } }>
								{ __( 'Selected images:', 'marqueeflow' ) } <strong>{ images.length }</strong>
								<span
									id="marqueeflow-editor-keyboard-hint"
									style={ { color: '#757575', marginLeft: '8px', fontSize: '0.8rem' } }
								>
									{ inputMode === 'keyboard'
										? __( '← → navigate, ↑ ↓ actions, Shift + ← → reorder', 'marqueeflow' )
										: __( 'Drag to reorder', 'marqueeflow' ) }
								</span>
							</p>
						<div className="marqueeflow-editor__sr-only" role="status" aria-live="polite">
							{ a11yMessage }
						</div>

						<div
							className="marqueeflow-editor__grid"
							ref={ gridRef }
							role="group"
							aria-label={ __( 'Reorder images', 'marqueeflow' ) }
							onKeyDownCapture={ handleGridKeyDownCapture }
						>
							{ images.map( ( image, index ) => (
								<div
									key={ image.id || index }
									data-thumb-index={ index }
									className={
										'marqueeflow-editor__thumb' +
										( dragIndex === index ? ' is-dragging' : '' )
									}
									tabIndex={ index === activeThumbIndex ? 0 : -1 }
									aria-label={ sprintf( __( 'Image %1$d of %2$d', 'marqueeflow' ), index + 1, images.length ) }
									aria-describedby="marqueeflow-editor-keyboard-hint"
									onFocus={ () => setActiveThumbIndex( index ) }
									onPointerDown={ ( e ) => handlePointerDown( e, index ) }
									onPointerMove={ handlePointerMove }
									onPointerUp={ handlePointerUp }
									style={ { touchAction: 'none' } }
								>
									<img
										src={ image.url }
										alt={ image.alt }
									/>
									<button
										className="marqueeflow-editor__remove"
										onClick={ () => removeImage( index ) }
										onFocus={ () => setActiveThumbIndex( index ) }
										type="button"
										tabIndex={ -1 }
										aria-label={ __( 'Remove image', 'marqueeflow' ) }
									>
										✕
									</button>
								</div>
							) ) }
						</div>
					</div>
				) }
			</div>
		</>
	);
}
