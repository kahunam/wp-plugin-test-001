/**
 * Featured Image Helper - Settings Page
 *
 * React-based settings interface using WordPress Gutenberg components
 *
 * @package Featured_Image_Helper
 */

import { render, useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import {
	Panel,
	PanelBody,
	PanelRow,
	TextControl,
	SelectControl,
	ToggleControl,
	Button,
	Notice,
	Spinner,
} from '@wordpress/components';

/**
 * Settings Page Component
 */
const SettingsPage = () => {
	const [settings, setSettings] = useState({
		gemini_api_key: '',
		prompt_style: 'photographic',
		aspect_ratio: '16:9',
		content_source: 'title',
		auto_generate: false,
		use_semantic_transformation: true,
	});
	const [saving, setSaving] = useState(false);
	const [testing, setTesting] = useState(false);
	const [notice, setNotice] = useState(null);

	// Load settings on mount
	useEffect(() => {
		apiFetch({ path: '/wp/v2/settings' })
			.then((response) => {
				setSettings({
					gemini_api_key: response.fih_gemini_api_key || '',
					prompt_style: response.fih_prompt_style || 'photographic',
					aspect_ratio: response.fih_aspect_ratio || '16:9',
					content_source: response.fih_content_source || 'title',
					auto_generate: response.fih_auto_generate || false,
					use_semantic_transformation: response.fih_use_semantic_transformation !== false,
				});
			})
			.catch((error) => {
				showNotice(__('Failed to load settings', 'featured-image-helper'), 'error');
			});
	}, []);

	const showNotice = (message, type = 'success') => {
		setNotice({ message, type });
		setTimeout(() => setNotice(null), 5000);
	};

	const handleSave = () => {
		setSaving(true);

		const payload = {
			fih_gemini_api_key: settings.gemini_api_key,
			fih_prompt_style: settings.prompt_style,
			fih_aspect_ratio: settings.aspect_ratio,
			fih_content_source: settings.content_source,
			fih_auto_generate: settings.auto_generate,
			fih_use_semantic_transformation: settings.use_semantic_transformation,
		};

		apiFetch({
			path: '/wp/v2/settings',
			method: 'POST',
			data: payload,
		})
			.then(() => {
				showNotice(__('Settings saved successfully!', 'featured-image-helper'));
				setSaving(false);
			})
			.catch((error) => {
				showNotice(__('Failed to save settings', 'featured-image-helper'), 'error');
				setSaving(false);
			});
	};

	const handleTestConnection = () => {
		setTesting(true);

		apiFetch({
			path: '/fih/v1/test-connection',
			method: 'POST',
		})
			.then((response) => {
				if (response.success) {
					showNotice(__('API connection successful!', 'featured-image-helper'));
				} else {
					showNotice(response.message || __('Connection test failed', 'featured-image-helper'), 'error');
				}
				setTesting(false);
			})
			.catch((error) => {
				showNotice(__('Connection test failed', 'featured-image-helper'), 'error');
				setTesting(false);
			});
	};

	return (
		<div className="fih-settings-page">
			<h1>{__('Featured Image Helper Settings', 'featured-image-helper')}</h1>

			{notice && (
				<Notice status={notice.type} isDismissible onRemove={() => setNotice(null)}>
					{notice.message}
				</Notice>
			)}

			<Panel>
				<PanelBody title={__('API Configuration', 'featured-image-helper')} initialOpen={true}>
					<PanelRow>
						<TextControl
							label={__('Gemini API Key', 'featured-image-helper')}
							value={settings.gemini_api_key}
							onChange={(value) => setSettings({ ...settings, gemini_api_key: value })}
							type="password"
							help={__(
								'Enter your Google Gemini API key. Get one at https://makersuite.google.com/app/apikey',
								'featured-image-helper'
							)}
							className="fih-full-width"
						/>
					</PanelRow>
					<PanelRow>
						<Button
							variant="secondary"
							onClick={handleTestConnection}
							isBusy={testing}
							disabled={!settings.gemini_api_key || testing}
						>
							{testing ? __('Testing...', 'featured-image-helper') : __('Test Connection', 'featured-image-helper')}
						</Button>
					</PanelRow>
				</PanelBody>

				<PanelBody title={__('Image Generation', 'featured-image-helper')} initialOpen={true}>
					<PanelRow>
						<SelectControl
							label={__('Prompt Style', 'featured-image-helper')}
							value={settings.prompt_style}
							options={[
								{ label: __('Photographic', 'featured-image-helper'), value: 'photographic' },
								{ label: __('Illustration', 'featured-image-helper'), value: 'illustration' },
								{ label: __('Abstract', 'featured-image-helper'), value: 'abstract' },
								{ label: __('Minimal', 'featured-image-helper'), value: 'minimal' },
							]}
							onChange={(value) => setSettings({ ...settings, prompt_style: value })}
							help={__('Visual style for generated images', 'featured-image-helper')}
						/>
					</PanelRow>

					<PanelRow>
						<SelectControl
							label={__('Aspect Ratio', 'featured-image-helper')}
							value={settings.aspect_ratio}
							options={[
								{ label: '16:9 (Landscape)', value: '16:9' },
								{ label: '1:1 (Square)', value: '1:1' },
								{ label: '9:16 (Portrait)', value: '9:16' },
								{ label: '4:3 (Standard)', value: '4:3' },
								{ label: '3:4 (Portrait)', value: '3:4' },
							]}
							onChange={(value) => setSettings({ ...settings, aspect_ratio: value })}
							help={__('Aspect ratio for generated images', 'featured-image-helper')}
						/>
					</PanelRow>

					<PanelRow>
						<SelectControl
							label={__('Content Source', 'featured-image-helper')}
							value={settings.content_source}
							options={[
								{ label: __('Post Title', 'featured-image-helper'), value: 'title' },
								{ label: __('Post Excerpt', 'featured-image-helper'), value: 'excerpt' },
								{ label: __('Post Content', 'featured-image-helper'), value: 'content' },
							]}
							onChange={(value) => setSettings({ ...settings, content_source: value })}
							help={__('Source content for prompt generation', 'featured-image-helper')}
						/>
					</PanelRow>
				</PanelBody>

				<PanelBody title={__('Advanced Options', 'featured-image-helper')} initialOpen={false}>
					<PanelRow>
						<ToggleControl
							label={__('Auto-generate on publish', 'featured-image-helper')}
							checked={settings.auto_generate}
							onChange={(value) => setSettings({ ...settings, auto_generate: value })}
							help={__(
								'Automatically generate featured image when publishing posts without one',
								'featured-image-helper'
							)}
						/>
					</PanelRow>

					<PanelRow>
						<ToggleControl
							label={__('Use semantic transformation', 'featured-image-helper')}
							checked={settings.use_semantic_transformation}
							onChange={(value) => setSettings({ ...settings, use_semantic_transformation: value })}
							help={__(
								'Transform post titles into abstract visual concepts using AI semantic analysis',
								'featured-image-helper'
							)}
						/>
					</PanelRow>
				</PanelBody>
			</Panel>

			<div className="fih-settings-footer">
				<Button variant="primary" onClick={handleSave} isBusy={saving} disabled={saving}>
					{saving ? __('Saving...', 'featured-image-helper') : __('Save Settings', 'featured-image-helper')}
				</Button>
			</div>
		</div>
	);
};

// Render the settings page
document.addEventListener('DOMContentLoaded', () => {
	const container = document.getElementById('fih-settings-root');
	if (container) {
		render(<SettingsPage />, container);
	}
});
