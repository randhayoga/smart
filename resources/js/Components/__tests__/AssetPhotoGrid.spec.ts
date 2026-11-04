import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import AssetPhotoGrid from '../AssetPhotoGrid.vue';

describe('AssetPhotoGrid.vue', () => {
  it('renders placeholder image with opacity when no photos are provided', () => {
    const wrapper = mount(AssetPhotoGrid, {
      props: { photos: [] },
    });
    const img = wrapper.find('img');
    expect(img.exists()).toBe(true);
    expect(img.attributes('src')).toBe('/media/inventory/placeholder.jpg');
    expect(img.classes()).toContain('opacity-50');
  });

  it('renders single full-width image when 1 photo is provided', () => {
    const wrapper = mount(AssetPhotoGrid, {
      props: { photos: ['assets/unit1.jpg'] },
    });
    const imgs = wrapper.findAll('img');
    expect(imgs.length).toBe(1);
    expect(imgs[0].attributes('src')).toBe('/media/assets/unit1.jpg');
  });

  it('renders 2 side-by-side images when 2 or 3 photos are provided', () => {
    const wrapper2 = mount(AssetPhotoGrid, {
      props: { photos: ['assets/unit1.jpg', 'assets/unit2.jpg'] },
    });
    expect(wrapper2.findAll('img').length).toBe(2);
    expect(wrapper2.find('.grid-cols-2').exists()).toBe(true);

    const wrapper3 = mount(AssetPhotoGrid, {
      props: { photos: ['assets/unit1.jpg', 'assets/unit2.jpg', 'assets/unit3.jpg'] },
    });
    expect(wrapper3.findAll('img').length).toBe(2);
    expect(wrapper3.find('.grid-cols-2').exists()).toBe(true);
  });

  it('renders 4 images in a 2x2 grid when 4 or more photos are provided', () => {
    const wrapper = mount(AssetPhotoGrid, {
      props: { photos: ['u1.jpg', 'u2.jpg', 'u3.jpg', 'u4.jpg', 'u5.jpg'] },
    });
    const imgs = wrapper.findAll('img');
    expect(imgs.length).toBe(4);
    expect(wrapper.find('.grid-cols-2.grid-rows-2').exists()).toBe(true);
  });
});
