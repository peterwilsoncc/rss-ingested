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
	SelectControl,
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

const { rssIngestedSettings } = window.PWCC;

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
const TERM_LIST_QUERY = {
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
		termIds,
		displayPostContentRadio,
		displayPostContent,
		displayPostDate,
		displaySiteName,
		postLayout,
		columns,
		excerptLength,
	} = attributes;
	const { termQueryList: termList } = useSelect( ( select ) => {
		const { getEntityRecords } = select( coreStore );

		return {
			termQueryList: getEntityRecords(
				'taxonomy',
				rssIngestedSettings().taxonomy,
				TERM_LIST_QUERY
			),
		};
	}, [] );

	const dropdownMenuProps = useToolsPanelDropdownMenuProps();

	const termSuggestions =
		termList?.reduce(
			( accumulator, term ) =>
				( () => {
					accumulator.push( { value: term.id, label: term.name } );
					return accumulator;
				} )(),
			[ { value: 0, label: __( 'All sites', 'rss-ingested' ) } ]
		) ?? [];

	const selectTerms = ( newTerm ) => {
		if ( 0 === parseInt( newTerm, 10 ) ) {
			setAttributes( { termIds: [] } );
			return;
		}

		const term = termSuggestions.find(
			( element ) => element.value.toString() === newTerm.toString()
		);

		setAttributes( { termIds: [ { id: term.value, label: term.label } ] } );
	};

	return (
		<>
			<ToolsPanel
				label={ __( 'Display options', 'rss-ingested' ) }
				resetAll={ () =>
					setAttributes( {
						displayPostContent: false,
						displayPostContentRadio: 'excerpt',
						excerptLength: DEFAULT_EXCERPT_LENGTH,
						displayPostDate: false,
						displaySiteName: true,
					} )
				}
				dropdownMenuProps={ dropdownMenuProps }
			>
				<ToolsPanelItem
					hasValue={ () => !! displaySiteName }
					label={ __( 'Display site name', 'rss-ingested' ) }
					onDeselect={ () =>
						setAttributes( { displaySiteName: false } )
					}
					isShownByDefault
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Display site name', 'rss-ingested' ) }
						checked={ displaySiteName }
						onChange={ ( value ) =>
							setAttributes( { displaySiteName: value } )
						}
					/>
				</ToolsPanelItem>
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
						termIds: undefined,
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
						termIds?.length > 0
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
					/>
					<SelectControl
						label={ __( 'Syndicated site', 'rss-ingested' ) }
						value={ termIds?.[ 0 ]?.id }
						options={ termSuggestions }
						onChange={ selectTerms }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
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
		termIds,
		displayPostContentRadio,
		displayPostContent,
		displayPostDate,
		displaySiteName,
		postLayout,
		columns,
		excerptLength,
	} = attributes;
	const { latestPosts } = useSelect(
		( select ) => {
			const { getEntityRecords } = select( coreStore );
			const termQueryIds =
				termIds && termIds.length > 0
					? termIds.map( ( term ) => term.id )
					: [];
			const latestPostsQuery = Object.fromEntries(
				Object.entries( {
					syndicated_sites: termQueryIds,
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
					rssIngestedSettings().postType,
					latestPostsQuery
				),
			};
		},
		[ postsToShow, order, orderBy, termIds ]
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
			<div { ...blockProps }>
				{ displaySiteName && (
					<h2 className="pwcc-rss-ingested-block-latest-posts__site-name">
						{ termIds?.[ 0 ]?.label }
					</h2>
				) }
				<ul>
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
							excerptLength <
								excerpt.trim().split( ' ' ).length &&
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
										dateTime={ format(
											'c',
											post.date_gmt
										) }
										className="pwcc-rss-ingested-block-latest-posts__post-date"
									>
										{ dateI18n(
											dateFormat,
											post.date_gmt
										) }
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
			</div>
		</>
	);
}
