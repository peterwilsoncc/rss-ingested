/**
 * External dependencies
 */
import clsx from 'clsx';

/**
 * WordPress dependencies
 */
import {
	Placeholder,
	QueryControls,
	RadioControl,
	RangeControl,
	Spinner,
	ToggleControl,
	ToolbarGroup,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { __, _x, sprintf } from '@wordpress/i18n';
import { dateI18n, format, getSettings } from '@wordpress/date';
import {
	InspectorControls,
	BlockControls,
	useBlockProps,
} from '@wordpress/block-editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { pin, list, grid } from '@wordpress/icons';
import { store as coreStore } from '@wordpress/core-data';
import { store as noticeStore } from '@wordpress/notices';
import { useInstanceId, useViewportMatch } from '@wordpress/compose';
import { createInterpolateElement } from '@wordpress/element';

/**
 * Internal dependencies
 */
import {
	MIN_EXCERPT_LENGTH,
	MAX_EXCERPT_LENGTH,
	MAX_POSTS_COLUMNS,
	DEFAULT_EXCERPT_LENGTH,
} from './constants';

/**
 * Module Constants
 */
const CATEGORIES_LIST_QUERY = {
	per_page: -1,
	_fields: 'id,name',
	context: 'view',
};

function useToolsPanelDropdownMenuProps() {
	const isMobile = useViewportMatch( 'medium', '<' );
	return ! isMobile
		? {
				popoverProps: {
					placement: 'left-start',
					// For non-mobile, inner sidebar width (248px) - button width (24px) - border (1px) + padding (16px) + spacing (20px)
					offset: 259,
				},
		  }
		: {};
}

function Controls( { attributes, setAttributes, postCount } ) {
	const {
		postsToShow,
		order,
		orderBy,
		categories,
		displayPostContentRadio,
		displayPostContent,
		displayPostDate,
		postLayout,
		columns,
		excerptLength,
	} = attributes;
	const { categoriesList } = useSelect( ( select ) => {
		const { getEntityRecords } = select( coreStore );

		return {
			categoriesList: getEntityRecords(
				'taxonomy',
				'rss_syndicated_site', // @todo: Use the dynamic taxonomy.
				CATEGORIES_LIST_QUERY
			),
		};
	}, [] );

	const dropdownMenuProps = useToolsPanelDropdownMenuProps();

	const categorySuggestions =
		categoriesList?.reduce(
			( accumulator, category ) => ( {
				...accumulator,
				[ category.name ]: category,
			} ),
			{}
		) ?? {};
	const selectCategories = ( tokens ) => {
		const hasNoSuggestion = tokens.some(
			( token ) =>
				typeof token === 'string' && ! categorySuggestions[ token ]
		);
		if ( hasNoSuggestion ) {
			return;
		}
		// Categories that are already will be objects, while new additions will be strings (the name).
		// allCategories nomalizes the array so that they are all objects.
		const allCategories = tokens.map( ( token ) => {
			return typeof token === 'string'
				? categorySuggestions[ token ]
				: token;
		} );
		// We do nothing if the category is not selected
		// from suggestions.
		if ( allCategories.includes( null ) ) {
			return false;
		}
		setAttributes( { categories: allCategories } );
	};

	return (
		<>
			<ToolsPanel
				label={ __( 'Post content', 'rss-ingested' ) }
				resetAll={ () =>
					setAttributes( {
						displayPostContent: false,
						displayPostContentRadio: 'excerpt',
						excerptLength: DEFAULT_EXCERPT_LENGTH,
					} )
				}
				dropdownMenuProps={ dropdownMenuProps }
			>
				<ToolsPanelItem
					hasValue={ () => !! displayPostContent }
					label={ __( 'Display post content', 'rss-ingested' ) }
					onDeselect={ () =>
						setAttributes( { displayPostContent: false } )
					}
					isShownByDefault
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Display post content', 'rss-ingested' ) }
						checked={ displayPostContent }
						onChange={ ( value ) =>
							setAttributes( { displayPostContent: value } )
						}
					/>
				</ToolsPanelItem>
				{ displayPostContent && (
					<ToolsPanelItem
						hasValue={ () => displayPostContentRadio !== 'excerpt' }
						label={ __( 'Content length', 'rss-ingested' ) }
						onDeselect={ () =>
							setAttributes( {
								displayPostContentRadio: 'excerpt',
							} )
						}
						isShownByDefault
					>
						<RadioControl
							label={ __( 'Content length', 'rss-ingested' ) }
							selected={ displayPostContentRadio }
							options={ [
								{
									label: __( 'Excerpt', 'rss-ingested' ),
									value: 'excerpt',
								},
								{
									label: __( 'Full post', 'rss-ingested' ),
									value: 'full_post',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( {
									displayPostContentRadio: value,
								} )
							}
						/>
					</ToolsPanelItem>
				) }
				{ displayPostContent &&
					displayPostContentRadio === 'excerpt' && (
						<ToolsPanelItem
							hasValue={ () =>
								excerptLength !== DEFAULT_EXCERPT_LENGTH
							}
							label={ __(
								'Max number of words',
								'rss-ingested'
							) }
							onDeselect={ () =>
								setAttributes( {
									excerptLength: DEFAULT_EXCERPT_LENGTH,
								} )
							}
							isShownByDefault
						>
							<RangeControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __(
									'Max number of words',
									'rss-ingested'
								) }
								value={ excerptLength }
								onChange={ ( value ) =>
									setAttributes( { excerptLength: value } )
								}
								min={ MIN_EXCERPT_LENGTH }
								max={ MAX_EXCERPT_LENGTH }
							/>
						</ToolsPanelItem>
					) }
			</ToolsPanel>
			<ToolsPanel
				label={ __( 'Post meta', 'rss-ingested' ) }
				resetAll={ () =>
					setAttributes( {
						displayPostDate: false,
					} )
				}
				dropdownMenuProps={ dropdownMenuProps }
			>
				<ToolsPanelItem
					hasValue={ () => !! displayPostDate }
					label={ __( 'Display post date', 'rss-ingested' ) }
					onDeselect={ () =>
						setAttributes( { displayPostDate: false } )
					}
					isShownByDefault
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Display post date', 'rss-ingested' ) }
						checked={ displayPostDate }
						onChange={ ( value ) =>
							setAttributes( { displayPostDate: value } )
						}
					/>
				</ToolsPanelItem>
			</ToolsPanel>

			<ToolsPanel
				label={ __( 'Sorting and filtering', 'rss-ingested' ) }
				resetAll={ () =>
					setAttributes( {
						order: 'desc',
						orderBy: 'date',
						postsToShow: 5,
						categories: undefined,
						columns: 3,
					} )
				}
				dropdownMenuProps={ dropdownMenuProps }
			>
				<ToolsPanelItem
					hasValue={ () =>
						order !== 'desc' ||
						orderBy !== 'date' ||
						postsToShow !== 5 ||
						categories?.length > 0
					}
					label={ __( 'Sort and filter', 'rss-ingested' ) }
					onDeselect={ () =>
						setAttributes( {
							order: 'desc',
							orderBy: 'date',
							postsToShow: 5,
							categories: undefined,
						} )
					}
					isShownByDefault
				>
					<QueryControls
						{ ...{ order, orderBy } }
						numberOfItems={ postsToShow }
						onOrderChange={ ( value ) =>
							setAttributes( { order: value } )
						}
						onOrderByChange={ ( value ) =>
							setAttributes( { orderBy: value } )
						}
						onNumberOfItemsChange={ ( value ) =>
							setAttributes( { postsToShow: value } )
						}
						categorySuggestions={ categorySuggestions }
						onCategoryChange={ selectCategories }
						selectedCategories={ categories }
					/>
				</ToolsPanelItem>

				{ postLayout === 'grid' && (
					<ToolsPanelItem
						hasValue={ () => columns !== 3 }
						label={ __( 'Columns', 'rss-ingested' ) }
						onDeselect={ () =>
							setAttributes( {
								columns: 3,
							} )
						}
						isShownByDefault
					>
						<RangeControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Columns', 'rss-ingested' ) }
							value={ columns }
							onChange={ ( value ) =>
								setAttributes( { columns: value } )
							}
							min={ 2 }
							max={
								! postCount
									? MAX_POSTS_COLUMNS
									: Math.min( MAX_POSTS_COLUMNS, postCount )
							}
							required
						/>
					</ToolsPanelItem>
				) }
			</ToolsPanel>
		</>
	);
}

export default function LatestPostsEdit( { attributes, setAttributes } ) {
	const instanceId = useInstanceId( LatestPostsEdit );

	const {
		postsToShow,
		order,
		orderBy,
		categories,
		displayPostContentRadio,
		displayPostContent,
		displayPostDate,
		postLayout,
		columns,
		excerptLength,
	} = attributes;
	const { latestPosts } = useSelect(
		( select ) => {
			const { getEntityRecords } = select( coreStore );
			const catIds =
				categories && categories.length > 0
					? categories.map( ( cat ) => cat.id )
					: [];
			const latestPostsQuery = Object.fromEntries(
				Object.entries( {
					syndicated_sites: catIds,
					order,
					orderby: orderBy,
					per_page: postsToShow,
					_embed: 'wp:featuredmedia',
					ignore_sticky: true,
				} ).filter( ( [ , value ] ) => typeof value !== 'undefined' )
			);

			return {
				latestPosts: getEntityRecords(
					'postType',
					'rss_syndicated_post', // @todo: Use the dynamic post type.
					latestPostsQuery
				),
			};
		},
		[ postsToShow, order, orderBy, categories ]
	);

	// If a user clicks to a link prevent redirection and show a warning.
	const { createWarningNotice } = useDispatch( noticeStore );
	const showRedirectionPreventedNotice = ( event ) => {
		event.preventDefault();
		createWarningNotice(
			__( 'Links are disabled in the editor.', 'rss-ingested' ),
			{
				id: `block-library/core/latest-posts/redirection-prevented/${ instanceId }`,
				type: 'snackbar',
			}
		);
	};

	const hasPosts = !! latestPosts?.length;
	const inspectorControls = (
		<InspectorControls>
			<Controls
				attributes={ attributes }
				setAttributes={ setAttributes }
				postCount={ latestPosts?.length ?? 0 }
			/>
		</InspectorControls>
	);

	const blockProps = useBlockProps( {
		className: clsx( {
			'pwcc-rss-ingested-block-latest-posts__list': true,
			'is-grid': postLayout === 'grid',
			'has-dates': displayPostDate,
			[ `columns-${ columns }` ]: postLayout === 'grid',
		} ),
	} );

	if ( ! hasPosts ) {
		return (
			<div { ...blockProps }>
				{ inspectorControls }
				<Placeholder
					icon={ pin }
					label={ __( 'Latest Posts', 'rss-ingested' ) }
				>
					{ ! Array.isArray( latestPosts ) ? (
						<Spinner />
					) : (
						__( 'No posts found.', 'rss-ingested' )
					) }
				</Placeholder>
			</div>
		);
	}

	// Removing posts from display should be instant.
	const displayPosts =
		latestPosts.length > postsToShow
			? latestPosts.slice( 0, postsToShow )
			: latestPosts;

	const layoutControls = [
		{
			icon: list,
			title: _x( 'List view', 'Latest posts block display setting' ),
			onClick: () => setAttributes( { postLayout: 'list' } ),
			isActive: postLayout === 'list',
		},
		{
			icon: grid,
			title: _x( 'Grid view', 'Latest posts block display setting' ),
			onClick: () => setAttributes( { postLayout: 'grid' } ),
			isActive: postLayout === 'grid',
		},
	];

	const dateFormat = getSettings().formats.date;

	return (
		<>
			{ inspectorControls }
			<BlockControls>
				<ToolbarGroup controls={ layoutControls } />
			</BlockControls>
			<ul { ...blockProps }>
				{ displayPosts.map( ( post ) => {
					const titleTrimmed = post.title.rendered.trim();
					let excerpt = post.excerpt.rendered;

					const excerptElement = document.createElement( 'div' );
					excerptElement.innerHTML = excerpt;

					excerpt =
						excerptElement.textContent ||
						excerptElement.innerText ||
						'';

					const needsReadMore =
						excerptLength < excerpt.trim().split( ' ' ).length &&
						post.excerpt.raw === '';

					const postExcerpt = needsReadMore ? (
						<>
							{ excerpt
								.trim()
								.split( ' ', excerptLength )
								.join( ' ' ) }
							{ createInterpolateElement(
								sprintf(
									/* translators: 1: Hidden accessibility text: Post title */
									__(
										'… <a>Read more<span>: %1$s</span></a>',
										'rss-ingested'
									),
									titleTrimmed ||
										__( '(no title)', 'rss-ingested' )
								),
								{
									a: (
										// eslint-disable-next-line jsx-a11y/anchor-has-content
										<a
											className="pwcc-rss-ingested-block-latest-posts__read-more"
											href={ post.link }
											rel="noopener noreferrer"
											onClick={
												showRedirectionPreventedNotice
											}
										/>
									),
									span: (
										<span className="screen-reader-text" />
									),
								}
							) }
						</>
					) : (
						excerpt
					);

					return (
						<li key={ post.id }>
							<a
								className="pwcc-rss-ingested-block-latest-posts__post-title"
								href={ post.link }
								rel="noreferrer noopener"
								dangerouslySetInnerHTML={
									!! titleTrimmed
										? {
												__html: titleTrimmed,
										  }
										: undefined
								}
								onClick={ showRedirectionPreventedNotice }
							>
								{ ! titleTrimmed
									? __( '(no title)', 'rss-ingested' )
									: null }
							</a>
							{ displayPostDate && post.date_gmt && (
								<time
									dateTime={ format( 'c', post.date_gmt ) }
									className="pwcc-rss-ingested-block-latest-posts__post-date"
								>
									{ dateI18n( dateFormat, post.date_gmt ) }
								</time>
							) }
							{ displayPostContent &&
								displayPostContentRadio === 'excerpt' && (
									<div className="pwcc-rss-ingested-block-latest-posts__post-excerpt">
										{ postExcerpt }
									</div>
								) }
							{ displayPostContent &&
								displayPostContentRadio === 'full_post' && (
									<div
										className="pwcc-rss-ingested-block-latest-posts__post-full-content"
										dangerouslySetInnerHTML={ {
											__html: post.content.raw.trim(),
										} }
									/>
								) }
						</li>
					);
				} ) }
			</ul>
		</>
	);
}
