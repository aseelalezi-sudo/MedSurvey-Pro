import 'package:flutter/material.dart';

/// Fills the whole [AppBar] — including the status-bar strip — with the brand
/// gradient.
///
/// [AppBar] lays its `flexibleSpace` out in a loose [Stack], so a decoration
/// without a child collapses to zero height. The bar then stays transparent
/// and white titles end up on the white page background. [SizedBox.expand]
/// forces the decoration to fill the available bar height.
class BrandAppBarBackground extends StatelessWidget {
  const BrandAppBarBackground({super.key, required this.gradient});

  final Gradient gradient;

  @override
  Widget build(BuildContext context) {
    return SizedBox.expand(
      child: DecoratedBox(decoration: BoxDecoration(gradient: gradient)),
    );
  }
}
