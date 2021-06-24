<template>
	<DashboardTimeline mode="home"
		:title="title" />
</template>

<script>
import DashboardTimeline from './DashboardTimeline'

export default {
	name: 'DashboardHomeTimeline',

	components: {
		DashboardTimeline,
	},

	props: {
		title: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
		}
	},

	computed: {
	},

	beforeMount() {
	},

	mounted() {
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
// nothing
</style>
